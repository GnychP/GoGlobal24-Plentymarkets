<?php

namespace GoGlobal24\Helpers;


use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;
use GoGlobal24\Helpers\Constants;

class Courier
{
    use Loggable;

    /**
     * @var HttpClient
     */
    public $client;

    private $config;

    private $request;

    public function __construct(HttpClient $client, ConfigRepository $configRepository)
    {
        $this->client = $client;
        $this->config = $configRepository;
        $this->client->setApiAccess($configRepository);
    }

    public function createReturn($referenceID, array $package, array $sender, string $additionalDescription)
    {
        $env = $this->config->get('GoGlobal24.env.type', Constants::ENV_DEV);

        if ($env == Constants::ENV_DEV) {
            $createShipment = $this->dummyCreateShipment($referenceID);
            if($createShipment['status'] == 'OK') {
              $getLabel = $this->dummyGetLabel($referenceID);
              return $getLabel['responses'][0];
            }
        }

        $this->request = [
            'CreateShipmentsRequest002' => [
              'shipmentType' => 'Return',
              'carrierName' => 'DE-DHL',
              'shipperAddress' => $sender,
              'receiverAddress' => [
          			'additionalDescription' => $additionalDescription
          		],
              'payerAddress' => null,
              'items' => [
          			[
          				'referenceID' => $referenceID,
          				'referenceID2' => '',
          				'type' => 'NP',
          				'weight' => $package['weight']
          			]
        			],
              'serviceLevel' => [
          			'service' => 'DST'
          		],
          		'shipmentDescription1' => $additionalDescription
          ]
        ];
        //createShipment request
        $createShipmentResponse = $this->client->post('create-shipment', $this->request);

        if ($this->client->getError()) {
          return false;
        }

        //get label request
        $getLabelResponse = $this->client->post('get-label', [
            'GetLabelRequest' => [
              'referenceID' => $referenceID,
              'collectiveLabel' => 1,
              'labelFormat' => 'PDF',
              'labelSize' => 'A6'
            ],
        ]);
        return $getLabelResponse['responses'][0];
    }

    public function createShipment($referenceID, array $package, array $receiver, string $additionalDescription)
    {
        $env = $this->config->get('GoGlobal24.env.type', Constants::ENV_DEV);

        if ($env == Constants::ENV_DEV) {
            $createShipment = $this->dummyCreateShipment($referenceID);
            if($createShipment['status'] == 'OK') {
              $getLabel = $this->dummyGetLabel($referenceID);
              return $getLabel['responses'][0];
            }
        }

        $countryCode = strtoupper($receiver['country']);
        $courier = $this->config->get("GoGlobal24.$countryCode.courier");
        $service = $this->config->get("GoGlobal24.$countryCode.service");
        $size = $this->config->get("GoGlobal24.$countryCode.size");

        $this->request = [
            'CreateShipmentsRequest002' => [
              'shipmentType' => 'Delivery',
              'carrierName' =>   $courier,
              'shipperAddress' => [
          			'additionalDescription' => $additionalDescription
          		],
              'receiverAddress' => $receiver,
              'payerAddress' => null,
              'items' => [
          			[
          				'referenceID' => $referenceID,
          				'referenceID2' => '',
          				'type' => $size,
          				'weight' => $package['weight']
          			]
        			],
              'serviceLevel' => [
          			'service' => $service
          		],
          		'shipmentDescription1' => $additionalDescription
          ]
        ];
        //createShipment request
        $createShipmentResponse = $this->client->post('create-shipment', $this->request);

        if ($this->client->getError()) {
          return false;
        }

        //get label request
        $getLabelResponse = $this->client->post('get-label', [
            'GetLabelRequest' => [
              'referenceID' => $referenceID,
              'collectiveLabel' => 1,
              'labelFormat' => 'PDF',
              'labelSize' => 'A6'
            ],
        ]);
        return $getLabelResponse['responses'][0];
    }

    public function getRequest()
    {
      return json_encode($this->request);
    }

    protected function dummyGetLabel($referenceID)
    {
      return [
        'responses' => [
          [
            'referenceID' => $referenceID,
            'labelData' => 'aHR0cHM6Ly9sYWJlbC5nb2dsb2JhbDI0LmNvbS8yMmQyMDBmODY3MGRiZGIzZTI1M2E5MGVlZTUwOTg0NzdjOTVjMjNkLzRlMjQ2MGE4MTRkODE4ODQ5M2E4OTdiZTdjMjZkNzQ3OWNmNTcwMDctSGwucGRm',
            'status' => 'OK',
            'labelFormat' => 'PDF',
            'trackingNo' => '60015062102',
            'barcodeScan' => '60015062102'
          ]
        ]
      ];
    }

    protected function dummyCreateShipment($referenceID)
    {
      return [
        'responses' => [
          [
            'referenceID' => $referenceID,
            'created' => '2020-10-01',
            'status' => 'OK',
            'id' => 25111,
            'message' => null
          ]
        ],
        'status' => 'OK',
        'message' => null
      ];
    }
}
