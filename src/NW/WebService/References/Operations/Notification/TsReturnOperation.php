<?php

namespace Operations\Notification;

use Contractor;
use Employee;
use Helpers\NotificationEvents;
use Seller;
use Status;
use Helpers\Functions;

class TsReturnOperation
{
    public const TYPE_NEW = 1;
    public const TYPE_CHANGE = 2;

    /**
     * @throws \Exception
     */
    public function doOperation(): array
    {
        $data = (array)$this->getRequest('data');
        $result = $this->initializeResult();

        $resellerId = $this->validateResellerId($data['resellerId']);
        $notificationType = $this->validateNotificationType($data['notificationType']);

        $reseller = $this->getReseller($resellerId);
        $client = $this->validateClient($data, $resellerId);
        $creator = $this->getEmployee($data['creatorId'], 'Creator');
        $expert = $this->getEmployee($data['expertId'], 'Expert');

        $templateData = $this->prepareTemplateData($data, $client, $creator, $expert, $notificationType, $resellerId);

        $this->validateTemplateData($templateData);

        $result = $this->sendNotifications($resellerId, $client, $notificationType, $data, $templateData, $result);

        return $result;
    }

    private function initializeResult(): array
    {
        return [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail' => false,
            'notificationClientBySms' => [
                'isSent' => false,
                'message' => '',
            ],
        ];
    }

    private function validateResellerId($resellerId): int
    {
        $resellerId = (int)$resellerId;
        if (empty($resellerId)) {
            throw new \Exception('Empty resellerId', 400);
        }
        return $resellerId;
    }

    private function validateNotificationType($notificationType): int
    {
        $notificationType = (int)$notificationType;
        if (empty($notificationType)) {
            throw new \Exception('Empty notificationType', 400);
        }
        return $notificationType;
    }

    private function getReseller(int $resellerId): Seller
    {
        $reseller = Seller::getById($resellerId);
        return $reseller;
    }

    private function validateClient(array $data, int $resellerId): Contractor
    {
        $client = Contractor::getById((int)$data['clientId']);
        if ($client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $resellerId) {
            throw new \Exception('Client not found!', 400);
        }
        return $client;
    }

    private function getEmployee(int $employeeId, string $role): Employee
    {
        $employee = Employee::getById($employeeId);
        return $employee;
    }

    private function prepareTemplateData(
        array $data,
        Contractor $client,
        Employee $creator,
        Employee $expert,
        int $notificationType,
        int $resellerId
    ): array {
        $clientName = $client->getFullName();
        if (empty($clientName)) {
            $clientName = $client->name;
        }

        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $differences = __('PositionStatusHasChanged', [
                'FROM' => Status::getName((int)$data['differences']['from']),
                'TO' => Status::getName((int)$data['differences']['to']),
            ], $resellerId);
        }

        return [
            'COMPLAINT_ID' => (int)$data['complaintId'],
            'COMPLAINT_NUMBER' => (string)$data['complaintNumber'],
            'CREATOR_ID' => (int)$data['creatorId'],
            'CREATOR_NAME' => $creator->getFullName(),
            'EXPERT_ID' => (int)$data['expertId'],
            'EXPERT_NAME' => $expert->getFullName(),
            'CLIENT_ID' => (int)$data['clientId'],
            'CLIENT_NAME' => $clientName,
            'CONSUMPTION_ID' => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER' => (string)$data['agreementNumber'],
            'DATE' => (string)$data['date'],
            'DIFFERENCES' => $differences,
        ];
    }

    private function validateTemplateData(array $templateData): void
    {
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }
    }

    private function sendNotifications(
        int $resellerId,
        Contractor $client,
        int $notificationType,
        array $data,
        array $templateData,
        array $result
    ): array {
        $emailFrom = (new \Helpers\Functions)->getResellerEmailFrom($resellerId);
        $emails = (new \Helpers\Functions)->getEmailsByPermit($resellerId, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage([
                    0 => [
                        'emailFrom' => $emailFrom,
                        'emailTo' => $email,
                        'subject' => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                        'message' => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
                $result['notificationEmployeeByEmail'] = true;
            }
        }

        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            if (!empty($emailFrom) && !empty($client->email)) {
                MessagesClient::sendMessage([
                    0 => [
                        'emailFrom' => $emailFrom,
                        'emailTo' => $client->email,
                        'subject' => __('complaintClientEmailSubject', $templateData, $resellerId),
                        'message' => __('complaintClientEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to']);
                $result['notificationClientByEmail'] = true;
            }

            if (!empty($client->mobile)) {
                $res = NotificationManager::send(
                    $resellerId,
                    $client->id,
                    NotificationEvents::CHANGE_RETURN_STATUS,
                    (int)$data['differences']['to'],
                    $templateData,
                    $error
                );
                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                }
                if (!empty($error)) {
                    $result['notificationClientBySms']['message'] = $error;
                }
            }
        }

        return $result;
    }
}
