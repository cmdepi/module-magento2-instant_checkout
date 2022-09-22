<?php
/**
 *
 * @description Instant checkout model
 *
 * @author Bina Commerce      <https://www.binacommerce.com>
 * @author C. M. de Picciotto <cmdepicciotto@binacommerce.com>
 *
 * @note This model was created with the intention of providing the possibility of carrying out the checkout circuit immediately (without the need for the client to enter or accept steps manually)
 * @note It is an util that must be managed for the correct life cycle of the entire instant checkout circuit: it is necessary to clean the checkout session once the instant checkout circuit is finished
 *
 * @see \Magento\Checkout\Controller\Onepage\Success::execute()
 *
 */
namespace Bina\InstantCheckout\Model;

use Exception;
use Psr\Log\LoggerInterface;
use Magento\Framework\DataObject\Copy;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Math\Random;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface   as EventManagerInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\Data\CustomerInterfaceFactory as CustomerDataFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Url;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\FormFactory          as CustomerFormFactory;
use Magento\Customer\Model\Metadata\FormFactory as CustomerMetadataFormFactory;
use Magento\Payment\Helper\Data    as PaymentHelper;
use Magento\Checkout\Helper\Data   as CheckoutHelper;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Payment\Model\Method\Free;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Bina\InstantCheckout\Api\CheckoutInterface;

class Checkout extends Onepage implements CheckoutInterface
{
    /**
     *
     * @var CartInterfaceFactory
     *
     */
    protected $_quoteFactory;

    /**
     *
     * @var PaymentHelper
     *
     */
    protected $_paymentHelper;

    /**
     *
     * Constructor
     *
     * @param CartInterfaceFactory          $quoteFactory
     * @param PaymentHelper                 $paymentHelper
     * @param EventManagerInterface         $eventManager
     * @param CheckoutHelper                $checkoutHelper
     * @param Url                           $customerUrl
     * @param LoggerInterface               $logger
     * @param CheckoutSession               $checkoutSession
     * @param CustomerSession               $customerSession
     * @param StoreManagerInterface         $storeManager
     * @param RequestInterface              $request
     * @param AddressFactory                $customrAddrFactory
     * @param CustomerFormFactory           $customerFormFactory
     * @param CustomerFactory               $customerFactory
     * @param OrderFactory                  $orderFactory
     * @param Copy                          $objectCopyService
     * @param MessageManagerInterface       $messageManager
     * @param CustomerMetadataFormFactory   $formFactory
     * @param CustomerDataFactory           $customerDataFactory
     * @param Random                        $mathRandom
     * @param EncryptorInterface            $encryptor
     * @param AddressRepositoryInterface    $addressRepository
     * @param AccountManagementInterface    $accountManagement
     * @param OrderSender                   $orderSender
     * @param CustomerRepositoryInterface   $customerRepository
     * @param CartRepositoryInterface       $quoteRepository
     * @param ExtensibleDataObjectConverter $extensibleDataObjectConverter
     * @param CartManagementInterface       $quoteManagement
     * @param DataObjectHelper              $dataObjectHelper
     * @param TotalsCollector               $totalsCollector
     *
     */
    public function __construct(
        CartInterfaceFactory          $quoteFactory,
        PaymentHelper                 $paymentHelper,
        EventManagerInterface         $eventManager,
        CheckoutHelper                $checkoutHelper,
        Url                           $customerUrl,
        LoggerInterface               $logger,
        CheckoutSession               $checkoutSession,
        CustomerSession               $customerSession,
        StoreManagerInterface         $storeManager,
        RequestInterface              $request,
        AddressFactory                $customrAddrFactory,
        CustomerFormFactory           $customerFormFactory,
        CustomerFactory               $customerFactory,
        OrderFactory                  $orderFactory,
        Copy                          $objectCopyService,
        MessageManagerInterface       $messageManager,
        CustomerMetadataFormFactory   $formFactory,
        CustomerDataFactory           $customerDataFactory,
        Random                        $mathRandom,
        EncryptorInterface            $encryptor,
        AddressRepositoryInterface    $addressRepository,
        AccountManagementInterface    $accountManagement,
        OrderSender                   $orderSender,
        CustomerRepositoryInterface   $customerRepository,
        CartRepositoryInterface       $quoteRepository,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        CartManagementInterface       $quoteManagement,
        DataObjectHelper              $dataObjectHelper,
        TotalsCollector               $totalsCollector
    ) {
        /**
         *
         * @note Init quote factory
         *
         */
        $this->_quoteFactory = $quoteFactory;

        /**
         *
         * @note Init payment helper
         *
         */
        $this->_paymentHelper = $paymentHelper;

        /**
         *
         * @note Parent constructor
         *
         */
        parent::__construct(
            $eventManager,
            $checkoutHelper,
            $customerUrl,
            $logger,
            $checkoutSession,
            $customerSession,
            $storeManager,
            $request,
            $customrAddrFactory,
            $customerFormFactory,
            $customerFactory,
            $orderFactory,
            $objectCopyService,
            $messageManager,
            $formFactory,
            $customerDataFactory,
            $mathRandom,
            $encryptor,
            $addressRepository,
            $accountManagement,
            $orderSender,
            $customerRepository,
            $quoteRepository,
            $extensibleDataObjectConverter,
            $quoteManagement,
            $dataObjectHelper,
            $totalsCollector
        );
    }

    /**
     *
     * Get quote object
     *
     * @return Quote
     *
     * @note This method is a rewrite of the parent method to avoid using the checkout session because it is not necessary for the instant checkout process
     *
     */
    public function getQuote()
    {
        /**
         *
         * @note Validate if quote is set
         *
         */
        if ($this->_quote === null) {
            /**
             *
             * @note Create quote
             *
             */
            /** @var Quote $quote */
            $quote = $this->_quoteFactory->create();
            $quote->setStoreId($this->_storeManager->getStore()->getId());

            /**
             *
             * @note Set quote
             *
             */
            $this->_quote = $quote;
        }

        /**
         *
         * @note Return quote
         *
         */
        return $this->_quote;
    }

    /**
     *
     * Execute checkout
     *
     * @param string                 $paymentMethod
     * @param Product                $product
     * @param CustomerInterface|null $customer
     * @param array|null             $productRequestInfo
     * @param bool|null              $shouldIgnoreBillingValidation
     * @param bool|null              $isPlaceable
     *
     * @return $this
     *
     * @throws Exception
     *
     */
    public function execute(
        $paymentMethod,
        $product,
        $customer                      = null,
        $productRequestInfo            = null,
        $shouldIgnoreBillingValidation = null,
        $isPlaceable                   = null
    ) {
        /**
         *
         * @note Try
         *
         */
        try {
            /**
             *
             * @note Assign customer to quote
             *
             */
            $this->_assignCustomer($customer);

            /**
             *
             * @note Init billing address
             *
             */
            $this->_initBillingAddress($shouldIgnoreBillingValidation);

            /**
             *
             * @note Validate if product is a virtual product
             *
             */
            if (!$product->isVirtual()) {
                /**
                 *
                 * @note If it is not a virtual product, init shipping address
                 *
                 */
                $this->_initShippingAddress();
            }

            /**
             *
             * @note Add product
             *
             */
            $this->_addProduct($product, $productRequestInfo);

            /**
             *
             * @note Init payment method
             *
             */
            $this->_initPaymentMethod($paymentMethod);

            /**
             *
             * @note Check if order is placeable
             * @note If it is a free quote then place order
             *
             */
            if ($isPlaceable || $this->_isFreeQuote()) {
                /**
                 *
                 * @note Save order
                 *
                 */
                $this->saveOrder();
            }
            else {
                /**
                 *
                 * @note Close quote
                 *
                 */
                $this->_closeQuote();
            }
        }

        /**
         *
         * @note Catch
         *
         */
        catch (Exception $e) {
            /**
             *
             * @note Validate if quote was created
             *
             */
            if ($this->getQuote()->getId()) {
                /**
                 *
                 * @note Disable quote
                 *
                 */
                $this->getQuote()->setIsActive(false);
                $this->quoteRepository->save($this->getQuote());
            }

            /**
             *
             * @note Throw exception
             *
             */
            throw new Exception($e->getMessage());
        }

        /**
         *
         * @note Return
         *
         */
        return $this;
    }

    /**
     *
     * Assign customer to quote
     *
     * @param CustomerInterface|null $customer
     *
     * @return void
     *
     * @throws LocalizedException
     *
     */
    protected function _assignCustomer($customer = null)
    {
        /**
         *
         * @note Check custom customer
         *
         */
        if (is_null($customer)) {
            /**
             *
             * @note If customer is not set, check if there is a logged in customer
             *
             */
            $customerSession = $this->getCustomerSession();
            $customer        = $customerSession->getCustomerDataObject();
        }

        /**
         *
         * @note Check customer
         *
         */
        if (!$customer->getId()) {
            throw new LocalizedException(__('To create an instant order, the customer must be logged in.'));
        }

        /**
         *
         * @note Assign customer
         *
         */
        $this->getQuote()->assignCustomer($customer);
    }

    /**
     *
     * Initialize quote billing address
     *
     * @param bool|null $shouldIgnoreBillingValidation
     *
     * @return void
     *
     */
    protected function _initBillingAddress($shouldIgnoreBillingValidation = null)
    {
        /**
         *
         * @note Check if it is necessary to validate billing address information
         *
         */
        if (!$shouldIgnoreBillingValidation) {
            /**
             *
             * @note Import customer default billing address
             *
             */
            $this->getQuote()->getBillingAddress()->importCustomerAddressData(
                $this->getCustomerSession()->getCustomer()->getDefaultBillingAddress()->getDataModel()
            );
        }
        else {
            /**
             *
             * @note Skip billing address validation
             *
             */
            $this->getQuote()->getBillingAddress()->setData('should_ignore_validation', true);

            /**
             *
             * @note Add customer generic data to billing address
             * @note For some reason, if this generic customer data is not set to the quote billing address, the order billing information breaks when someone tries to watch it on frontend/backend
             *
             */
            $customerSession = $this->getCustomerSession();
            $customer        = $customerSession->getCustomerDataObject();
            if ($customer->getId()) {
                $this->getQuote()->getBillingAddress()->setCustomerId($customer->getId());
                $this->getQuote()->getBillingAddress()->setEmail($customer->getEmail());
                $this->getQuote()->getBillingAddress()->setFirstname($customer->getFirstname());
                $this->getQuote()->getBillingAddress()->setLastname($customer->getLastname());
            }
        }
    }

    /**
     *
     * Initialize quote shipping address
     *
     * @return void
     *
     */
    protected function _initShippingAddress()
    {
        /**
         *
         * @note Import customer default shipping address
         *
         */
        $this->getQuote()->getShippingAddress()->importCustomerAddressData(
            $this->getCustomerSession()->getCustomer()->getDefaultShippingAddress()->getDataModel()
        );
    }

    /**
     *
     * Add product
     *
     * @param Product    $product
     * @param array|null $requestInfo
     *
     * @return void
     *
     */
    protected function _addProduct($product, $requestInfo = null)
    {
        /**
         *
         * @note Add product to quote
         *
         */
        $this->getQuote()->addProduct($product, $requestInfo);
        $this->getQuote()->collectTotals();
        $this->quoteRepository->save($this->getQuote());
    }

    /**
     *
     * Init payment method information
     *
     * @param string $paymentMethod
     *
     * @return void
     *
     */
    protected function _initPaymentMethod($paymentMethod)
    {
        /**
         *
         * @note Check if quote is free
         *
         */
        if ($this->_isFreeQuote()) {
            /**
             *
             * @note If it is a free quote then use free payment method
             *
             */
            $paymentMethod = Free::PAYMENT_METHOD_FREE_CODE;
        }

        /**
         *
         * @note Add payment method
         *
         */
        $data[PaymentInterface::KEY_METHOD] = $paymentMethod;

        /**
         *
         * @note Save payment information
         *
         */
        $this->savePayment($data);
    }

    /**
     *
     * Close quote
     *
     * @return void
     *
     */
    protected function _closeQuote()
    {
        /**
         *
         * @note Set quote as inactive
         *
         */
        $this->getQuote()->setIsActive(false);

        /**
         *
         * @note Reserve order ID
         *
         */
        $this->getQuote()->reserveOrderId();

        /**
         *
         * @note Save quote
         *
         */
        $this->quoteRepository->save($this->getQuote());
    }

    /**
     *
     * Validate if quote is free
     *
     * @return bool
     *
     */
    protected function _isFreeQuote()
    {
        /**
         *
         * @note Get free payment method
         *
         */
        $method = $this->_paymentHelper->getMethodInstance(Free::PAYMENT_METHOD_FREE_CODE);

        /**
         *
         * @note Check if free method is available
         * @note If it is available then it is a free quote
         *
         */
        return $method->isAvailable($this->getQuote());
    }
}
