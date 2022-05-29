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

    public function countLogs($file, $dataLogFile): object
    {
        $arrLogs = $this->getAllLogs($file, $dataLogFile);
        $objCountLogs = new stdClass();
        $objCountLogs->requests = 0;
        $objCountLogs->request_success = 0;
        $objCountLogs->request_errors = 0;

        foreach ($arrLogs as $log) {
            $isRequestTitle = str_contains($log->log_text, "'title' => '");
            $isRequestSuccess = str_contains($log->log_info['format'], 'production.INFO:');
            $isRequestError = str_contains($log->log_info['format'], 'production.ERROR:');

            // Count requests
            if ($isRequestTitle && ($isRequestSuccess || $isRequestError)) {
                ++$objCountLogs->requests;

                // Count request success
                if ($isRequestSuccess) {
                    ++$objCountLogs->request_success;
                }

                // Count request errors
                if ($isRequestError) {
                    ++$objCountLogs->request_errors;
                }
            }
        }

        return $objCountLogs;
    }

    public function getFileInfo($file): object
    {
        $objFileInfo = new stdClass();
        $objFileInfo->file_name = $file->getClientOriginalName();
        $objFileInfo->file_size = $this->filesizeFormatted($file->getSize());

        return $objFileInfo;
    }

    private function filesizeFormatted($bytes): string
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes .= ' bytes';
        } elseif ($bytes === 1) {
            $bytes .= ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }
}
