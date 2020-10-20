<?php

namespace GoGlobal24\Helpers;


use Plenty\Plugin\ConfigRepository;
use GoGlobal24\Helpers\PackageTypeHelper;

class HttpClient
{
    const RESPONSE_OK = 'OK';

    protected $username;
    protected $password;

    protected $baseUrl = 'https://gorest.goglobal24.com';

    private $response;

    /**
     * @param \Plenty\Plugin\ConfigRepository $configRepository
     */
    public function setApiAccess(ConfigRepository $configRepository)
    {
        $this->username = $configRepository->get('GoGlobal24.access.apiUsername');
        $this->password = $configRepository->get('GoGlobal24.access.apiPassword');
    }

    public function post($url, array $data)
    {
        $data['userName'] = $this->username;
        $data['password'] = $this->password;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$this->baseUrl}/{$url}");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);

        return $this->response = json_decode($response, true, 512, JSON_BIGINT_AS_STRING);
    }


    /**
     * @return bool|string
     */
    public function getError()
    {
        $error = $this->proccessError();
        if (!empty($error)) {
            return $this->prepareErrorMessage($error['msg'], $error['details']);
        }

        return false;
    }

    public function getLastResponse()
    {
        return $this->response;
    }

    /**
     * Curl fetch content.
     *
     * @param string $fileUrl
     * @return bool|string
     */
    public function download(string $fileUrl)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fileUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    public function proccessError()
    {
      if(isset($this->response['status'])) {
        if ($this->response['status'] == self::RESPONSE_OK) {
            return null;
        }
        $message = $this->response['message'];
        if(empty($message)){
          $message = $this->response['responses'][0]['message'];
        }
        if($message['content'] == 'ReferenceID already used.'){
          return null;
        }
        return [
            'msg' => "Code {$message['code']}",
            'details' => $message['content'],
        ];
      }
      if(isset($this->response['responses'])){
        return null;
      }
      return [
          'msg' => 'CODE 000',
          'details' => 'Unknow Error',
      ];
    }

    private function prepareErrorMessage($msg, $details): string
    {
        return "<br><br><br>
GoGlobal24 API Error message: <br>
$msg <br>
$details<br>";
    }

    public function getErrorMessage($msg, $details): array
    {
        return [
          'Message' => $msg,
          'Details' => $details
        ];
    }
}
