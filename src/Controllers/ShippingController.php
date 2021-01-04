<?php

namespace GoGlobal24\Controllers;


use DateTime;

use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Shipping\Package\Models\OrderShippingPackage;
use Plenty\Modules\Plugin\Storage\Contracts\StorageRepositoryContract;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Order\Shipping\PackageType\Models\ShippingPackageType;
use Plenty\Modules\Order\Shipping\Returns\Models\FailedRegisterOrderReturns;
use Plenty\Modules\Order\Shipping\Returns\Models\RegisterOrderReturnsResponse;
use Plenty\Modules\Order\Shipping\Returns\Models\SuccessfullyRegisteredOrderReturns;
use Plenty\Modules\Order\Shipping\Package\Contracts\OrderShippingPackageRepositoryContract;
use Plenty\Modules\Order\Shipping\Information\Contracts\ShippingInformationRepositoryContract;
use Plenty\Modules\Order\Shipping\PackageType\Contracts\ShippingPackageTypeRepositoryContract;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\ConfigRepository;

use GoGlobal24\Helpers\Constants;
use GoGlobal24\Helpers\PackageTypeHelper;
use GoGlobal24\Helpers\ApiHelper;
use GoGlobal24\Helpers\Courier;

class ShippingController extends Controller
{
    use Loggable;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var OrderRepositoryContract $orderRepository
     */
    private $orderRepository;

    /**
     * @var AddressRepositoryContract $addressRepository
     */
    private $addressRepository;

    /**
     * @var OrderShippingPackageRepositoryContract $orderShippingPackage
     */
    private $orderShippingPackage;

    /**
     * @var ShippingInformationRepositoryContract
     */
    private $shippingInformationRepositoryContract;

    /**
     * @var StorageRepositoryContract $storageRepository
     */
    private $storageRepository;

    /**
     * @var ShippingPackageTypeRepositoryContract
     */
    private $shippingPackageTypeRepositoryContract;

    /**
     * @var \Plenty\Modules\Item\Variation\Contracts\VariationRepositoryContract
     */
    private $variationRepositoryContract;

    /**
     * @var  array
     */
    private $createOrderResult = [];

    /**
     * @var ConfigRepository
     */
    private $config;

    /**
     * @var Courier
     */
    private $courier;

    /**
     * ShipmentController constructor.
     *
     * @param Request $request
     * @param OrderRepositoryContract $orderRepository
     * @param AddressRepositoryContract $addressRepositoryContract
     * @param OrderShippingPackageRepositoryContract $orderShippingPackage
     * @param StorageRepositoryContract $storageRepository
     * @param ShippingInformationRepositoryContract $shippingInformationRepositoryContract
     * @param ShippingPackageTypeRepositoryContract $shippingPackageTypeRepositoryContract
     * @param ConfigRepository $config
     * @param Courier $courier
     */
    public function __construct(
        Request $request,
        OrderRepositoryContract $orderRepository,
        AddressRepositoryContract $addressRepositoryContract,
        OrderShippingPackageRepositoryContract $orderShippingPackage,
        StorageRepositoryContract $storageRepository,
        ShippingInformationRepositoryContract $shippingInformationRepositoryContract,
        ShippingPackageTypeRepositoryContract $shippingPackageTypeRepositoryContract,
        ConfigRepository $config,
        Courier $courier
    )
    {
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->addressRepository = $addressRepositoryContract;
        $this->orderShippingPackage = $orderShippingPackage;
        $this->storageRepository = $storageRepository;

        $this->shippingInformationRepositoryContract = $shippingInformationRepositoryContract;
        $this->shippingPackageTypeRepositoryContract = $shippingPackageTypeRepositoryContract;

        $this->config = $config;

        $this->courier = $courier;
    }

    public function registerReturns(Request $request, $orderIds)
    {
        $orderIds = $this->getOrderIds($request, $orderIds);
        /** @var RegisterOrderReturnsResponse $response */
        $response = pluginApp(RegisterOrderReturnsResponse::class);

        foreach ($orderIds as $orderId) {
            $order = $this->orderRepository->findOrderById($orderId);

            $returnSender = $this->getReceiver($order);
            $additionalDescription = $this->config->get('GoGlobal24.extra.additionalDescription');
            $env = $this->config->get('GoGlobal24.env.type', Constants::ENV_DEV);

            $shippingPackages = $this->orderShippingPackage->listOrderShippingPackages($order->id);
            foreach ($shippingPackages as $shippingPackage) {

                $packageType = $this->shippingPackageTypeRepositoryContract->findShippingPackageTypeById($shippingPackage->packageId);

                $requestPackage = $this->prepareRequestPackage($shippingPackage, $packageType, $order);

                $referenceID = $orderId.strtotime("now");

                $GoGlobalResponse = $this->courier->createReturn($referenceID, $requestPackage, $returnSender, $additionalDescription);
                $this->getLogger(Constants::PLUGIN_NAME)->error('GoGlobal24 Return: Response result', $GoGlobalResponse);

                if ($env == Constants::ENV_PROD && $this->courier->client->getError()) {
                    $this->getLogger(Constants::PLUGIN_NAME)->error('GoGlobal24 Return: Error response', $this->courier->client->getLastResponse());
                    $this->getLogger(Constants::PLUGIN_NAME)->error('GoGlobal24 Return: Cannot create shipment', $this->courier->client->getError());
                    $this->getLogger(Constants::PLUGIN_NAME)->error('GoGlobal24 Return: Cannot create shipment - request', $this->courier->getRequest());
                    /** @var FailedRegisterOrderReturns $failed */
                    $failed = pluginApp(FailedRegisterOrderReturns::class);
                    $failed->setOrderId($orderId);
                    $failed->addErrorMessage($this->courier->client->getError());
                    $response->addFailedRegisterOrderReturns($failed);
                    return $response;
                }

                $reference = $GoGlobalResponse['referenceID'];
                $trackingNo = $GoGlobalResponse['trackingNo'];
                $labelUrl = base64_decode($GoGlobalResponse['labelData']);
                $labelBase64 = base64_encode($this->courier->client->download($labelUrl));

                $storageKey = "return_{$reference}.pdf";


                $this->getLogger(Constants::PLUGIN_NAME)
                    ->info(
                        'Return: storage data', [
                            'labelUrl' => $labelUrl,
                            'return_order_id' => $orderId,
                            'external_id' => $trackingNo,
                            'reference_id' => $reference,
                            'additionalDescription' => $additionalDescription
                        ]
                    );

                /** @var SuccessfullyRegisteredOrderReturns $success */
                $success = pluginApp(SuccessfullyRegisteredOrderReturns::class);

                $success->setOrderId($orderId);
                $success->setFileName($storageKey);
                $success->setLabelBase64($labelBase64);
                $success->setAvailableUntil(date('Y-m-d H:i:s', strtotime(date("Y-m-d H:i:s") . " + 90 day")));
                $success->setExternalNumber($trackingNo);
                $success->setExternalData(
                    [
                        'url_label_pdf' => $labelUrl,
                        'tracking_no' => $trackingNo,
                        'reference_id' => $reference,
                        'isBase64' => (base64_decode($labelBase64, true)) ? 'true' : 'false',
                    ]
                );

                $response->addSuccessfullyRegisteredReturns($success);
            }
        }

        return $response;
    }

    /**
     * Returns all order ids from request object
     *
     * @param Request $request
     * @param $orderIds
     * @return array
     */
    private function getOrderIds(Request $request, $orderIds)
    {
        if (is_numeric($orderIds)) {
            $orderIds = [$orderIds];
        } else if (!is_array($orderIds)) {
            $orderIds = $request->get('orderIds');
        }

        return $orderIds;
    }

    /**
     * @param \Plenty\Modules\Order\Shipping\Package\Models\OrderShippingPackage $package
     * @param \Plenty\Modules\Order\Shipping\PackageType\Models\ShippingPackageType $packageType
     * @param \Plenty\Modules\Order\Models\Order $order
     * @return array
     */
    private function prepareRequestPackage(OrderShippingPackage $package, ShippingPackageType $packageType, Order $order): array
    {
        $weight = $package->weight / 1000;
        $package = [
            'weight' => ($weight > 0.5) ? round($weight, 2) : 1,
            'size_l' => $packageType->length,
            'size_w' => $packageType->width,
            'size_d' => $packageType->height,
            'content' => $order->id,
            'note1' => 'note1',
            'note2' =>  'note2',
        ];
        return $package;
    }

    /**
     * @param \Plenty\Modules\Order\Models\Order $order
     * @return array
     */
    private function getReceiver(Order $order): array
    {
        /** @var \Plenty\Modules\Account\Address\Models\Address $deliveryAddress */
        $deliveryAddress = $order->deliveryAddress;
        /** @var \Plenty\Modules\Order\Shipping\Countries\Models\Country $country */
        $country = $deliveryAddress->country;
        $receiver = [
            'nameOrCompany' => (empty($deliveryAddress->companyName)) ? "{$deliveryAddress->firstName} {$deliveryAddress->lastName}" :  $deliveryAddress->companyName,
            'additionalDescription' => $deliveryAddress->additional,
            'address1' => "{$deliveryAddress->street} {$deliveryAddress->houseNumber}",
            'address2' => '',
            'city' => $deliveryAddress->town,
            'zipCode' => $deliveryAddress->postalCode,
            'region' => '',
            'country' => $country->isoCode2,
            'email' => $deliveryAddress->email,
            'phone' => $deliveryAddress->phone,
            'taxId' => '',
            'pickupId' => null
        ];

        return $receiver;
    }

    /**
     * Retrieves the label file from a given URL and saves it in S3 storage
     *
     * @param string $body
     * @param string $key
     * @return \Plenty\Modules\Cloud\Storage\Models\StorageObject
     */
    private function saveLabelToS3($body, $key)
    {
        return $this->storageRepository->uploadObject(Constants::PLUGIN_NAME, $key, $body, true);

    }
}
