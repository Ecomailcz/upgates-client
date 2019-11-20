<?php declare(strict_types=1);


namespace EcomailUpgates;

use EcomailUpgates\Exception\EcomailUpgatesNotFound;
use EcomailUpgates\Exception\EcomailUpgatesAnotherError;
use EcomailUpgates\Exception\EcomailUpgatesInvalidAuthorization;
use EcomailUpgates\Exception\EcomailUpgatesRequestError;
use Exception;


class Client
{
    /**
     * UPgates api login
     *
     * @var string
     */
    private $login;

    /**
     * UPgates api password
     *
     * @var string
     */
    private $pass;

    /**
     * UPgatess project-name
     *
     * @var string
     */
    private $projectName;


    public function __construct(string $login, string $pass, string $projectName)
    {
        $this->projectName = $projectName;
        $this->login = $login;
        $this->pass = $pass;
    }

    /**
     * @param string $httpMethod
     * @param string $url
     * @param array $queryParameters
     * @return array
     * @throws EcomailUpgatesAnotherError
     * @throws EcomailUpgatesInvalidAuthorization
     * @throws EcomailUpgatesNotFound
     * @throws EcomailUpgatesRequestError
     */
    public function makeRequest(string $httpMethod, string $url, array $queryParameters = []): array
    {
        $logins = sprintf('%s:%s', $this->login, $this->pass);
        /** @var resource $ch */
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        curl_setopt($ch, CURLOPT_HTTPAUTH, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($logins),
            'Content-Type: application/json',
        ]);
        if (count($queryParameters) !== 0) {
            $url .= '?' . http_build_query($queryParameters);
        }
        curl_setopt($ch, CURLOPT_URL, sprintf('https://%s.admin.upgates.com/api/v2/%s', $this->projectName, $url));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Ecomail.cz UPgates client');


        $output = curl_exec($ch);

        if ($output === false) {
            throw new Exception(curl_error($ch), curl_errno($ch));
        }

        $result = json_decode($output, true);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200 && curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 201) {

            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 404) {
                throw new EcomailUpgatesNotFound();
            }
            // Check authorization
            elseif (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 401) {
                throw new EcomailUpgatesInvalidAuthorization($this->login);
            } elseif (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 400) {
                if (isset($result['errors']) && sizeof($result['errors']) > 0) {
                    foreach ($result['errors'] as $error) {
                        throw new EcomailUpgatesRequestError($error['message']);
                    }

                }

            }
        }

        if (!$result) {
            return [];
        }

        if (array_key_exists('success', $result) && !$result['success']) {
            throw new EcomailUpgatesAnotherError($result);
        }
        return $result;
    }
}