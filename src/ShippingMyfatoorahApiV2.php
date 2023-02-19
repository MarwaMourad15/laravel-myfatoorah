<?php

namespace MarwaMourad15\LaravelPaymentMyfatoorah;

/**
 * This class handles the shipping process of MyFatoorah API endpoints
 *
 * @author    MyFatoorah <tech@myfatoorah.com>
 * @copyright 2021 MyFatoorah, All rights reserved
 * @license   GNU General Public License v3.0
 */
class ShippingMyfatoorahApiV2 extends MyfatoorahApiV2 {
    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get MyFatoorah Shipping Countries (GET API)
     *
     * @return object
     */
    public function getShippingCountries() {

        $url  = "$this->apiURL/v2/GetCountries";
        $json = $this->callAPI($url, null, null, 'Get Countries');
        return $json;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Get Shipping Cities (GET API)
     *
     * @param integer $method      [1 for DHL, 2 for Aramex]
     * @param string  $countryCode It can be obtained from getShippingCountries
     * @param string  $searchValue The key word that will be used in searching
     *
     * @return object
     */
    public function getShippingCities($method, $countryCode, $searchValue = '') {

        $url = $this->apiURL . '/v2/GetCities'
                . '?shippingMethod=' . $method
                . '&countryCode=' . $countryCode
                . '&searchValue=' . urlencode(substr($searchValue, 0, 30));

        $json = $this->callAPI($url, null, null, "Get Cities: $countryCode");
        //        return array_map('strtolower', $json->Data->CityNames);
        return $json;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------

    /**
     * Calculate Shipping Charge (POST API)
     *
     * @param array $curlData the curl data contains the shipping information
     *
     * @return object
     */
    public function calculateShippingCharge($curlData) {

        $url  = "$this->apiURL/v2/CalculateShippingCharge";
        $json = $this->callAPI($url, $curlData, null, 'Calculate Shipping Charge');
        return $json;
    }

    //-----------------------------------------------------------------------------------------------------------------------------------------
}
