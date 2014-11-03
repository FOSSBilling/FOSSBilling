<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


class Model_ProductPaymentTable
{
    /**
     * @deprecated moved to Product\Service
     */
    public function getTypes()
    {
        return array(
            Model_ProductPayment::FREE      =>  'Free',
            Model_ProductPayment::ONCE      =>  'One time',
            Model_ProductPayment::RECURRENT =>  'Recurrent',
        );
    }

    /**
     * @deprecated moved to Product\Service
     */
    public function getStartingFromPrice(Model_ProductPayment $model)
    {
        if($model->type == 'free') {
            return 0;
        }

        if($model->type == 'once') {
            return $model->once_price;
        }

        if($model->type == 'recurrent') {
            $p = array();
            
            if($model->w_enabled) {
                $p[] = $model->w_price;
            }
            
            if($model->m_enabled) {
                $p[] = $model->m_price;
            }

            if($model->q_enabled) {
                $p[] = $model->q_price;

            }

            if($model->b_enabled) {
                $p[] = $model->b_price;
            }

            if($model->a_enabled) {
                $p[] = $model->a_price;
            }

            if($model->bia_enabled) {
                $p[] = $model->bia_price;
            }

            if($model->tria_enabled) {
                $p[] = $model->tria_price;
            }
            return min($p);
        }

        return NULL;
    }

    /**
     * @deprecated moved to Product\Service::toProductPaymentApiArray
     */
    public function toApiArray($model)
    {
        $periods = array();
        $periods['1W'] = array('price'=>$model->w_price, 'setup'=>$model->w_setup_price, 'enabled'=>$model->w_enabled);
        $periods['1M'] = array('price'=>$model->m_price, 'setup'=>$model->m_setup_price, 'enabled'=>$model->m_enabled);
        $periods['3M'] = array('price'=>$model->q_price, 'setup'=>$model->q_setup_price, 'enabled'=>$model->q_enabled);
        $periods['6M'] = array('price'=>$model->b_price, 'setup'=>$model->b_setup_price, 'enabled'=>$model->b_enabled);
        $periods['1Y'] = array('price'=>$model->a_price, 'setup'=>$model->a_setup_price, 'enabled'=>$model->a_enabled);
        $periods['2Y'] = array('price'=>$model->bia_price, 'setup'=>$model->bia_setup_price, 'enabled'=>$model->bia_enabled);
        $periods['3Y'] = array('price'=>$model->tria_price, 'setup'=>$model->tria_setup_price, 'enabled'=>$model->tria_enabled);
        
        return array(
            'type' =>   $model->type,
            Model_ProductPayment::FREE      => array('price'=>0, 'setup'=>0),
            Model_ProductPayment::ONCE      => array('price'=>$model->once_price, 'setup'=>$model->once_setup_price),
            Model_ProductPayment::RECURRENT => $periods,
        );
    }
}