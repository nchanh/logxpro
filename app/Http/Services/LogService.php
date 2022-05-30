<?php

namespace App\Http\Services;

use stdClass;

class LogService
{
    public function getAllLogs($file, $dataLogFile): array
    {
        $logMatches = $this->getLogMatches($file);

        $arrLogs = [];
        $logTemp = new stdClass();
        $logTemp->log_info = [];
        $logTemp->log_text = '';

        $indexLogMatches = -1;
        foreach ($dataLogFile as $line => $log) {
            $indexTemp = count($logMatches) === ($indexLogMatches + 1) ? $indexLogMatches : $indexLogMatches + 1;
            $isFirstArray = str_contains($log, $logMatches[$indexTemp]['format']);

            if ($isFirstArray) {
                $indexLogMatches++;
                $logTemp->log_info = $logMatches[$indexLogMatches];
            }

            $isEndArray = $line === count($dataLogFile) - 1;
            if (!$isEndArray && count($logMatches) !== ($indexLogMatches + 1)) {
                $isEndArray = str_contains($dataLogFile[$line + 1], $logMatches[$indexLogMatches + 1]['format']);
            }

            if (!$isFirstArray && $log !== '') {
                $logTemp->log_text .= $log;
            }

            if ($isEndArray) {
                $arrLogs[] = $logTemp;
                $logTemp = new stdClass();
                $logTemp->log_info = [];
                $logTemp->log_text = '';
            }
        }

        return $arrLogs;
    }

    public function getLogMatches($file): array
    {
        $pattern = "/^\[(?<date>.*)\]\s(?<env>\w+)\.(?<type>\w+):(?<message>.*)/m";
        $content = file_get_contents($file);
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER, 0);

        $logs = [];
        foreach ($matches as $line => $match) {
            $logs[] = [
                'timestamp' => $match['date'],
                'env' => $match['env'],
                'type' => $match['type'],
                'message' => trim($match['message']),
                'line' => $line,
                'title' => $match[0],
                'format' => "[${match['date']}] ${match['env']}.${match['type']}:"
            ];
        }

        return $logs;
    }

    public function countLogs($inputFile, $dataLogFile): object
    {
        $arrLogs = $this->getAllLogs($inputFile, $dataLogFile);
        $users = $this->getUserLogs($arrLogs);
        $countLogs = new stdClass();
        $countLogs->requests = 0;
        $countLogs->request_success = 0;
        $countLogs->request_errors = 0;
        $countLogs->request_users = [];

        foreach ($arrLogs as $log) {
            $logText = $log->log_text;
            $logInfo = $log->log_info;
            $isRequestTitle = str_contains($logText, "'title' => '");
            $isRequestSuccess = str_contains($logInfo['format'], 'production.INFO:');
            $isRequestError = str_contains($logInfo['format'], 'production.ERROR:');

            // Count requests
            if ($isRequestTitle && ($isRequestSuccess || $isRequestError)) {
                ++$countLogs->requests;

                // Count request success
                if ($isRequestSuccess) {
                    ++$countLogs->request_success;
                }

                // Count request errors
                if ($isRequestError) {
                    ++$countLogs->request_errors;
                }

                // Count request users
                $userId = $this->userMatchInLog($users, $logText);
                if ($userId !== '') {
                    if (!array_key_exists($userId, $countLogs->request_users)) {
                        $countLogs->request_users["${userId}"] = new stdClass();
                        $countLogs->request_users["${userId}"]->all = 0;
                        $countLogs->request_users["${userId}"]->success = 0;
                        $countLogs->request_users["${userId}"]->errors = 0;
                    }

                    ++$countLogs->request_users["${userId}"]->all;

                    if ($isRequestSuccess) {
                        ++$countLogs->request_users["${userId}"]->success;
                    }

                    if ($isRequestError) {
                        ++$countLogs->request_users["${userId}"]->errors;
                    }
                }
            }
        }

        // Sort request users
        arsort($countLogs->request_users);

        return $countLogs;
    }

    public function getInfoFile($inputFile): object
    {
        $file = new stdClass();
        $file->name = $inputFile->getClientOriginalName();
        $file->size = $this->filesizeFormatted($inputFile->getSize());

        return $file;
    }

    private function filesizeFormatted($bytes): string
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes >= 1) {
            $bytes .= ' bytes';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    public function getUserLogs($arrLogs): array
    {
        $users = [];
        $lengthTitleUser = 11;
        $lengthTitleUserId = 14;
        $logTitleUser = "'user' => '";
        $logTitleUserId = "'user_id' => '";

        foreach ($arrLogs as $log) {
            $logText = $log->log_text;
            $isUser = str_contains($logText, $logTitleUser);
            $isUserId = str_contains($logText, $logTitleUserId);
            $userId = '';

            if ($isUser) {
                $userId = $this->getUserLog($logText, $logTitleUser, $lengthTitleUser);
            }

            if ($isUserId) {
                $userId = $this->getUserLog($logText, $logTitleUserId, $lengthTitleUserId);
            }

            if (str_contains($userId, '-')) {
                $users[] = $userId;
            }
        }

        return array_unique($users);
    }

    private function getUserLog($logText, $logTitleUser, $lengthTitleUser): string
    {
        $startLengthUser = strpos($logText, $logTitleUser) + $lengthTitleUser;
        $stringSubUser = substr($logText, $startLengthUser);
        $endLengthUser = strpos($stringSubUser, "',");

        return substr($logText, $startLengthUser, $endLengthUser);
    }

    private function userMatchInLog($users, $logText) {
        $userId = '';
        foreach ($users as $user) {
            if (str_contains($logText, $user)) {
                $userId = $user;
                break;
            }
        }

        return $userId;
    }
}
