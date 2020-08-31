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

namespace Box\Mod\Servicedownloadable;
use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di = null;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    public function attachOrderConfig(\Model_Product $product, array &$data)
    {
        $c = json_decode($product->config, 1);
        $required = array(
            'filename' => 'Product is not configured completely.',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $c);

        $data['filename'] = $c['filename'];
        return array_merge($c, $data);
    }
    
    public function validateOrderData(array &$data)
    {
        $required = array(
            'filename' => 'Filename is missing in product config',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
    }

    /**
     * @param \Model_ClientOrder $order
     * @return \Model_ServiceDownloadable
     */
    public function action_create(\Model_ClientOrder $order)
    {
        $c = json_decode($order->config, 1);
        if (!is_array($c)){
            throw new \Box_Exception(sprintf('Order #%s config is missing', $order->id));
        }
        $this->validateOrderData($c);

        $model = $this->di['db']->dispense('ServiceDownloadable');
        $model->client_id = $order->client_id;
        $model->filename = $c['filename'];
        $model->downloads = 0;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return $model;
    }

    public function action_activate(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     *
     * @todo
     * @param \Model_ClientOrder $order
     * @return boolean
     */
    public function action_renew(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     *
     * @todo
     * @param \Model_ClientOrder $order
     * @return boolean
     */
    public function action_suspend(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     *
     * @todo
     * @param \Model_ClientOrder $order
     * @return boolean
     */
    public function action_unsuspend(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     *
     * @todo
     * @param \Model_ClientOrder $order
     * @return boolean
     */
    public function action_cancel(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     *
     * @todo
     * @param \Model_ClientOrder $order
     * @return boolean
     */
    public function action_uncancel(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     *
     * @todo
     * @param \Model_ClientOrder $order
     * @return void
     */
    public function action_delete(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);
        if($service instanceof \Model_ServiceDownloadable) {
            $this->di['db']->trash($service);
        }
    }

    public function hitDownload(\Model_ServiceDownloadable $model)
    {
        $model->downloads += 1;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
    }

    public function toApiArray(\Model_ServiceDownloadable $model, $deep = false, $identity = null)
    {
        $productService = $this->di['mod_service']('product');
        $result = array(
            'path'          => $productService->getSavePath($model->filename),
            'filename'      => $model->filename,
        );
        
        if($identity instanceof \Model_Admin) {
            $result['downloads'] = $model->downloads;
        }
        
        return $result;
    }

    public function uploadProductFile(\Model_Product $productModel)
    {
        $request = $this->di['request'];
        if ($request->hasFiles() == 0) {
            throw new \Box_Exception('Error uploading file');
        }
        $files = $request->getUploadedFiles();
        $file = $files[0];

        $productService = $this->di['mod_service']('product');
        move_uploaded_file($file->getRealPath(), $productService->getSavePath($file->getName()));
        // End upload

        $config = json_decode($productModel->config, 1);
        $productService->removeOldFile($config);

        $config['filename'] = $file->getName();
        $productModel->config = json_encode($config);
        $productModel->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($productModel);

        $this->di['logger']->info('Uploaded new file for product %s', $productModel->id);
        return true;
    }

    /**
     * @param \Model_ServiceDownloadable $serviceDownloadable
     * @param \Model_ClientOrder $order
     * @return bool
     * @throws \Box_Exception
     */
    public function updateProductFile(\Model_ServiceDownloadable $serviceDownloadable, \Model_ClientOrder $order)
    {
        $request = $this->di['request'];
        if ($request->hasFiles() == 0) {
            throw new \Box_Exception('Error uploading file');
        }
        $productService = $this->di['mod_service']('product');
        $files = $request->getUploadedFiles();
        $file = $files[0];
        move_uploaded_file($file->getRealPath(), $productService->getSavePath($file->getName()));
        // End upload

        $serviceDownloadable->filename = $file->getName();
        $serviceDownloadable->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($serviceDownloadable);

        $this->di['logger']->info('Uploaded new file for order %s', $order->id);
        return true;
    }

    private function _error_message($error_code)
    {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension';
            default:
                return 'Unknown upload error';
        }
    }

    public function sendDownload($filename, $path) {
        if(APPLICATION_ENV == 'testing') {
            return ;
        }

        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=$filename".";");
        header("Content-Transfer-Encoding: binary");
        readfile($path);
        flush();
    }

    public function sendFile(\Model_ServiceDownloadable $serviceDownloadable)
    {
        $info = $this->toApiArray($serviceDownloadable);
        $filename = $info['filename'];
        $path = $info['path'];
        if(!$this->di['tools']->fileExists($path)) {
            throw new \Box_Exception('File can not be downloaded at the moment. Please contact support', null, 404);
        }
        $this->hitDownload($serviceDownloadable);
        $this->sendDownload($filename, $path);

        $this->di['logger']->info('Downloaded service %s file', $serviceDownloadable->id);
        return true;
    }
}