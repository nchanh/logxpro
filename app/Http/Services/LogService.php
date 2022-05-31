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
        $countLogs->request_token_errors = 0;
        $countLogs->request_slow_query = 0;
        $countLogs->request_time_out = 0;
        $countLogs->request_users = [];
        $countLogs->request_other = [];

        foreach ($arrLogs as $log) {
            $logText = $log->log_text;
            $logInfo = $log->log_info;
            $isRequestTitle = str_contains($logText, "'title' => '") ||
                str_contains($logText, "'info' => '")  ||
                str_contains($logText, "'user' => '")  ||
                str_contains($logText, "'user_id' => '");
            $isRequestSuccess = str_contains($logInfo['format'], 'production.INFO:');
            $isRequestError = str_contains($logInfo['format'], 'production.ERROR:');
            $isRequestTokenError = str_contains($logText, "'title' => 'TOKEN_ERROR'");
            $isRequestSlowQuery = str_contains($logInfo['title'], "[SLOW QUERY]");
            $isRequestTimeOut = str_contains($logText, "'MAX_EXECUTION_TIME_ERROR'");

            // Count requests
            if ($isRequestTitle && ($isRequestSuccess || $isRequestError)) {
                ++$countLogs->requests;

                if ($isRequestSuccess) {
                    ++$countLogs->request_success;
                }

                if ($isRequestError) {
                    ++$countLogs->request_errors;
                }

                if ($isRequestTokenError) {
                    ++$countLogs->request_token_errors;
                }

                if ($isRequestTimeOut) {
                    ++$countLogs->request_time_out;
                }

                // Count request users
                $userId = $this->userMatchInLog($users, $logText);
                if ($userId !== '') {
                    if (!array_key_exists($userId, $countLogs->request_users)) {
                        $countLogs->request_users[(string)$userId] = new stdClass();
                        $countLogs->request_users[(string)$userId]->all = 0;
                        $countLogs->request_users[(string)$userId]->success = 0;
                        $countLogs->request_users[(string)$userId]->errors = 0;
                        $countLogs->request_users[(string)$userId]->ios = 0;
                        $countLogs->request_users[(string)$userId]->android = 0;
                        $countLogs->request_users[(string)$userId]->web = 0;
                    }

                    ++$countLogs->request_users[(string)$userId]->all;

                    if ($isRequestSuccess) {
                        ++$countLogs->request_users[(string)$userId]->success;
                    }

                    if ($isRequestError) {
                        ++$countLogs->request_users[(string)$userId]->errors;
                    }

                    if (str_contains($logText, "'user_agent' => '")) {
                        ++$countLogs->request_users[(string)$userId]->web;
                    }

                    if (str_contains($logText, "'device' =>")) {
                        if (str_contains($logText, "'os' => 'iOS'")) {
                            ++$countLogs->request_users[(string)$userId]->ios;
                        }

                        if (str_contains($logText, "'os' => 'Android'")) {
                            ++$countLogs->request_users[(string)$userId]->android;
                        }
                    }
                }
            } else if ($isRequestSlowQuery) {
                ++$countLogs->request_slow_query;
            } else if (
                !empty($logText) &&
                !str_contains($logText, "The token has been blacklisted") &&
                !str_contains($logText, "file_put_contents()") &&
                !str_contains($logText, "Host: graph.microsoft.com") &&
                !str_contains($logText, "Host: www.googleapis.com") &&
                !str_contains($logText, "Tymon\JWTAuth\Http\Middleware\BaseMiddleware->checkForToken()") &&
                !str_contains($logText, "Stack trace:") &&
                !str_contains($logText, "[stacktrace]") &&
                !str_contains($logText, "Tymon\JWTAuth\Manager->decode()")
            ) {
                $countLogs->request_other[] = $logText;

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
