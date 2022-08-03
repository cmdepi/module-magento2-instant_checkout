<?php
/**
 *
 * @description Instant checkout interface
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
namespace Bina\InstantCheckout\Api;

use Exception;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote;

interface CheckoutInterface
{
    /**
     *
     * Get quote object
     *
     * @return Quote
     *
     * @note This method is a rewrite of the parent method to avoid using the checkout session because it is not necessary for the instant checkout process
     *
     */
    public function getQuote();

    /**
     *
     * Process
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
    public function process(
        $paymentMethod,
        $product,
        $customer                      = null,
        $productRequestInfo            = null,
        $shouldIgnoreBillingValidation = null,
        $isPlaceable                   = null
    );
}
