<?php

declare(strict_types=1);

namespace App\Matchmaker\Service;

use App\Clients\Factories\CityEndpointRequestFactory;
use App\Clients\Factories\CityFactory;
use App\Clients\Factories\RegionEndpointRequestFactory;
use App\Clients\Factories\RegionFactory;
use App\Clients\OfficeApiClient;
use App\Clients\Resources\City;
use App\Clients\Resources\Region;
use App\Clients\Utils\ResponseUtil;
use App\Geo\PDOK\Address;
use App\Geo\PDOK\AddressFactory;
use App\Geo\PDOK\Exception\PostcodeNotFoundException;
use App\Geo\PDOK\PDOKLocatieServerClientInterface;
use App\Geo\PDOK\PDOKRequestFactory;
use App\Geo\PDOK\Postcode;
use App\Geo\PDOK\PostcodeFactory;
use App\Geo\PostalCode;
use App\Geo\PostalCodeFactory;
use App\Matchmaker\Location;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use Symfony\Component\HttpFoundation\Response;

class LocationService
{
    /** @var PDOKLocatieServerClientInterface */
    private $PDOKLocatieServerClient;
    /** @var OfficeApiClient */
    private $officeApiClient;
    /** @var PDOKRequestFactory */
    private $PDOKRequestFactory;
    /** @var CityEndpointRequestFactory */
    private $cityEndpointRequestFactory;
    /** @var RegionEndpointRequestFactory */
    private $regionEndpointRequestFactory;
    /** @var PostcodeFactory */
    private $postcodeFactory;
    /** @var AddressFactory */
    private $addressFactory;
    /** @var CityFactory */
    private $cityFactory;
    /** @var RegionFactory */
    private $regionFactory;
    /** @var PostalCodeFactory */
    private $postalCodeFactory;

    public function __construct(
        PDOKLocatieServerClientInterface $PDOKLocatieServerClient,
        PDOKRequestFactory $PDOKRequestFactory,
        OfficeApiClient $officeApiClient,
        CityEndpointRequestFactory $cityEndpointRequestFactory,
        RegionEndpointRequestFactory $regionEndpointRequestFactory,
        PostcodeFactory $postcodeFactory,
        AddressFactory $addressFactory,
        CityFactory $cityFactory,
        RegionFactory $regionFactory,
        PostalCodeFactory $postalCodeFactory
    ) {
        $this->PDOKLocatieServerClient = $PDOKLocatieServerClient;
        $this->PDOKRequestFactory = $PDOKRequestFactory;
        $this->officeApiClient = $officeApiClient;
        $this->cityEndpointRequestFactory = $cityEndpointRequestFactory;
        $this->regionEndpointRequestFactory = $regionEndpointRequestFactory;
        $this->postcodeFactory = $postcodeFactory;
        $this->addressFactory = $addressFactory;
        $this->cityFactory = $cityFactory;
        $this->regionFactory = $regionFactory;
        $this->postalCodeFactory = $postalCodeFactory;
    }

    /**
     * @throws PostcodeNotFoundException
     */
    public function search(string $query): array
    {
        $locations = [];

        $postalCode = $this->postalCodeFactory->make($query);

        if ($postalCode === null) {
            $cities = $this->getCities($query);

            foreach ($cities as $city) {
                $locations[] = new Location(
                    null,
                    null,
                    $city,
                    null,
                    null,
                    null
                );
            }

            return $locations;
        }

        $promises = [
            'postcode' => $this->makePostcodePromise($postalCode),
            'city' => $this->makeCityPromise($postalCode),
        ];

        $responses = Utils::settle($promises)->wait();

        $postcode = $promises['postcode'] === null ? null : $this->getPostcode($responses['postcode']);

        if ($postcode === null) {
            throw new Exception('Postcode does not exist.');
        }

        $locations[] = new Location(
            null,
            null,
            $promises['city'] === null ? null : $this->getCity($responses['city']),
            null,
            (string) $postalCode,
            null
        );

        return $locations;
    }

    public function getLocation(?PostalCode $postalCode, ?string $houseNumber, ?string $regionName): Location
    {
        $promises = [
            'address' => $this->makeAddressPromise($postalCode, $houseNumber),
            'city' => $this->makeCityPromise($postalCode),
            'region' => $this->makeRegionPromise($regionName),
        ];

        $responses = Utils::settle($promises)->wait();

        return new Location(
            null,
            $promises['region'] === null ? null : $this->getRegion($responses['region']),
            $promises['city'] === null ? null : $this->getCity($responses['city']),
            $promises['address'] === null ? null : $this->getAddress($responses['address']),
            (string) $postalCode,
            $houseNumber
        );
    }

    private function makeAddressPromise(?PostalCode $postalCode, ?string $houseNumber): ?PromiseInterface
    {
        if ($postalCode === null || $houseNumber === null) {
            return null;
        }

        $request = $this->PDOKRequestFactory->makeFindAddressesByPostcodeAndHouseNumberRequest(
            (string) $postalCode,
            $houseNumber
        );

        return $this->PDOKLocatieServerClient->sendAsyncHttpRequest($request);
    }

    private function makePostcodePromise(?PostalCode $postalCode): ?PromiseInterface
    {
        if ($postalCode === null) {
            return null;
        }

        $postcode = $postalCode->getDigits();
        if ($postalCode->getLetters() === null || strlen($postalCode->getLetters()) === 2) {
            $postcode .= $postalCode->getLetters();
        }

        $request = $this->PDOKRequestFactory->makeFindPostcodesRequest($postcode);

        return $this->PDOKLocatieServerClient->sendAsyncHttpRequest($request);
    }

    private function makeCityPromise(?PostalCode $postalCode): ?PromiseInterface
    {
        if ($postalCode === null) {
            return null;
        }

        $request = $this->cityEndpointRequestFactory->makeFindByPostcodeRequest($postalCode->getDigits());

        return $this->officeApiClient->sendAsyncHttpRequest($request);
    }

    private function makeRegionPromise(?string $regionName): ?PromiseInterface
    {
        if ($regionName === null) {
            return null;
        }

        $request = $this->regionEndpointRequestFactory->makeTryGetByNameRequest($regionName);

        return $this->officeApiClient->sendAsyncHttpRequest($request);
    }

    private function getAddress(array $response): ?Address
    {
        $response = ResponseUtil::handleAwaitedResponse($response);

        $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        $addresses = array_map(
            [$this->addressFactory, 'makeFromApiResult'],
            $json['response']['docs'] ?? []
        );

        return $addresses[0] ?? null;
    }

    /**
     * @throws PostcodeNotFoundException
     */
    private function getPostcode(array $response): Postcode
    {
        $response = ResponseUtil::handleAwaitedResponse($response);

        $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        $docs = $json['response']['docs'] ?? [];

        if (count($docs) === 0) {
            throw new PostcodeNotFoundException();
        }

        $postcodes = array_map(
            [$this->postcodeFactory, 'makeFromApiResult'],
            $docs
        );

        return $postcodes[0];
    }

    private function getCity(array $response): ?City
    {
        try {
            $response = ResponseUtil::handleAwaitedResponse($response);

            $json = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);

            return $this->cityFactory->makeFromApiResult($json->data);
        } catch (ClientException $exception) {
            if ($exception->getCode() === Response::HTTP_NOT_FOUND) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * @return City[]
     */
    private function getCities(string $name): array
    {
        $request = $this->cityEndpointRequestFactory->makeGetByNameRequest($name);

        $response = $this->officeApiClient->sendHttpRequest($request);

        $json = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);

        return array_map(
            [$this->cityFactory, 'makeFromApiResult'],
            $json->data
        );
    }

    private function getRegion(array $response): ?Region
    {
        $response = ResponseUtil::handleAwaitedResponse($response);

        $json = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);

        if ($json->region === null) {
            return null;
        }

        return $this->regionFactory->makeFromApiResult($json->region);
    }
}
