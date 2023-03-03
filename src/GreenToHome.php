<?php declare(strict_types=1);

namespace NewMobilityEnterprise;

// use GreenToHome\Api\CompanyApi;
// use GreenToHome\Model\Parcel;
// use GreenToHome\Model\Customer;
// use GreenToHome\Model\Address;

use Shopware\Core\Framework\Plugin;
// use Swagger\Client\Configuration;
// use Shopware\Core\Framework\Context;
// use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
// use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
// use Shopware\Core\System\SystemConfig\SystemConfigService;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

class GreenToHome extends Plugin
{
    // private SystemConfigService $systemConfigService;
    // private String $gthEnv;
    // private String $apiKey;
    // private String $cField;

    // public function install(InstallContext $context): void
    // {
    //     $customFieldSetRepository = $this->container->get('custom_field_set.repository');

    //     $customFieldSetRepository->create(
    //         [
    //             [
    //                 'name' => 'deposit',
    //                 'config' => [
    //                     'label' => [
    //                         'de-DE' => 'Pfand',
    //                         'en-GB' => 'Deposit',
    //                     ],
    //                 ],
    //                 'customFields' => [
    //                     [
    //                         'name' => 'deposit',
    //                         'type' => CustomFieldTypes::FLOAT,
    //                         'config' => [
    //                             'min' => 0,
    //                             'label' => [
    //                                 'de-DE' => 'Pfandbetrag',
    //                                 'en-GB' => 'Deposit value',
    //                             ],
    //                         ],
    //                     ],
    //                     [
    //                         'name' => 'deposittype',
    //                         'type' => CustomFieldTypes::TEXT,
    //                         'config' => [
    //                             'label' => [
    //                                 'de-DE' => 'Pfandtyp',
    //                                 'en-GB' => 'Deposit type',
    //                             ],
    //                         ],
    //                     ],
    //                 ],
    //                 'relations' => [
    //                     [
    //                         'entityName' => 'product',
    //                     ],
    //                 ],
    //             ],
    //         ],
    //         $context->getContext()
    //     );

    // }

    // public function uninstall(UninstallContext $context): void
    // {
    //     $customFieldSetRepository = $this->container->get('custom_field_set.repository');

    //     $entitites = $customFieldSetRepository->search(
    //         (new Criteria())->addFilter(
    //             new MultiFilter(
    //                 MultiFilter::CONNECTION_OR,
    //                 [
    //                     new EqualsFilter('name', 'deposit'),
    //                     new EqualsFilter('name', 'deposittype'),
    //                 ]
    //             )
    //         ),
    //         \Shopware\Core\Framework\Context::createDefaultContext()
    //     );

    //     foreach ($entitites->getEntities() as $_entityId => $_entityData) {
    //         $customFieldSetRepository->delete(
    //             [
    //                 ['id' => $_entityId],
    //             ],
    //             \Shopware\Core\Framework\Context::createDefaultContext()
    //         );
    //     }
    // }

    // public function __construct(
    //     SystemConfigService $systemConfigService,
    //     EntityRepositoryInterface $orderRepository,
    //     EntityRepositoryInterface $orderCustomerRepository,
    //     EntityRepositoryInterface $orderAddressRepository,
    //     EntityRepositoryInterface $countryRepository,
    // ) {
    //     parent::__construct();
    //     $this->systemConfigService = $systemConfigService;
    //     $this->cField = $this->systemConfigService->get('GreenToHome.config.customFieldName');

    //     $this->orderRepository = $orderRepository;
    //     $this->orderCustomerRepository = $orderCustomerRepository;
    //     $this->orderAddressRepository = $orderAddressRepository;
    //     $this->countryRepository = $countryRepository;
    // }

    // public function getUnsubmittedOrders(): EntityRepositoryInterface
    // {
    //     $context = Context::createDefaultContext();

    //     $criteria = new Criteria();
    //     $criteria->addFilter( 
    //         new AndFilter([
    //             new EqualsFilter( 'customFields.' . $this->cField, null ),
    //             new EqualsAnyFilter('stateMachineState.technicalName', ['open', 'in_progress']),
    //         ])
    //     );

    //     $results = null;
    //     try {
    //         $results = $repository->search($this->criteria, $context); // TODO : Make sure to use the correct Entity repository
    //     } catch (Exception $e) {
    //         dump ('Exception when searching for unsyncronized orders: ');
    //         dump (e->getMessage());
    //     }

    //     return $results;
    // }

    // public function processAllOrders(): int
    // {
    //     print_r("$this->gthEnv $this->apiKey");

    //     $errors = [];
    //     $config = Configuration::getDefaultConfiguration()->setApiKey('key', $this->apiKey);
    //     $orders = $this->getUnsubmittedOrders();

    //     foreach ($orders as $order) {
    //         $orderCriteria = (new Criteria())->addFilter(new EqualsFilter('orderId', $order->id));
    //         $customer = $this->orderCustomerRepository->search($orderCriteria, $context)->first();
    //         $address = $this->orderAddressRepository->search($orderCriteria, $context)->first();

    //         $apiInstance = new CompanyApi(
    //             // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    //             // This is optional, `GuzzleHttp\Client` will be used as default.
    //             new \GuzzleHttp\Client,
    //             $config
    //         );

    //         $parcelWeight = 1;
    //         $parcelWidth = 1;
    //         $parcelLenght = 1;
    //         $parcelPackagingUnits = 1;

    //         // Configuring Parcel
    //         $parcel = new Parcel(); // \GreenToHome\Model\Parcel | Parcel to submit
    //         $parcel->setExternalReference($order->orderNumber);
    //         $parcel->setComment($order->customerComment);
    //         $parcel->setWeight($parcelWeight); // ######### - Don't exist in the order - #########
    //         $parcel->setWidth($parcelWidth); // ######### - Don't exist in the order - #########
    //         $parcel->setLenght($parcelLenght); // ######### - Don't exist in the order - #########
    //         $parcel->setPackagingUnits($parcelPackagingUnits); // ######### ??? - What is this - ??? #########

    //         $customer = new Customer(); // \GreenToHome\Model\Customer
    //         $customer->setName($customer->firstName . ' ' . $customer->lastName); // or $order->customer->firstName . " " . $order->customer->lastName
    //         $customer->setEmail($customer->email);
    //         $customer->setPhone($address->phoneNumber);

    //         $customerAddress = new Address(); // \GreenToHome\Model\Address
    //         $customerAddress->setCountry($this->countryRepository->search((new Criteria())->addFilter(new EqualsFilter('id', $address->countryId)), $context)->first()->name);
    //         $customerAddress->setCity($address->city);
    //         $customerAddress->setZip($address->zipcode);
    //         $customerAddress->setStreet($address->street);
    //         $customerAddress->setComment(($address->additionalAddressLine1) ? ' ' . $address->additionalAddressLine1 : '') . (($address->additionalAddressLine2) ? ', ' . $address->additionalAddressLine2 : '');

    //         $customer->setAddress($customerAddress);
    //         $parcel->setTo($customer);

    //         // Sending configured Parcel to GTH Backend
    //         try {
    //             $result = $apiInstance->submitParcel($parcel);

    //             $customFields = $order->getCustomFields();
    //             $customFields[$this->cField] = $result->getParcel()->getId();
    //             $this->orderRepository->update([['id' => $order->id, 'customFields'=>$customFields]], $context);

    //             $output->writeln('GTH-Paketnummer: ', $gthParcelId, PHP_EOL);
    //         } catch (Exception $e) {
    //             $errors[]= 'Exception when calling CompanyApi->submitParcel() for id:' . $order->id . ' : ' . $e->getMessage() . PHP_EOL;
    //         }
    //     }

    //     if (count($errors) > 0) 
    //     {
    //         $output->writeln($errors);
    //         // Exit code 1 for error
    //         return 1;
    //     }

    //     $output->writeln(' - The above orders exported successfully!');
    //     // Exit code 0 for success
    //     return 0;
    // }
}
