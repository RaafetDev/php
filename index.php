<?php
require 'vendor/autoload.php';
use Jcupitt\Vips;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Content-Type: application/json');

$app = new \Slim\App();

$container = $app->getContainer();
$container['errorHandler'] = function ($container) {
    return function ($request, $response, $exception) use ($container) {
        $data = [
            'status' => false,
            'msg' => 'Internal Server Error'
        ];
        return $response->withStatus(500)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode($data));
    };
};

$container['notFoundHandler'] = function ($container) {
    return function ($request, $response) use ($container) {
        $data = [
            'status' => false,
            'msg' => 'Route not found'
        ];
        return $response->withStatus(404)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode($data));
    };
};

$app->get('/', function ($request, $response, $args) {
    $data = [
        'status' => true,
        'server' => 'ON',
        'by' => 'RedBLs',
        'telegram' => 'https://t.me/bls_script_alert'
    ];
    return $response->withJson($data);
});

$app->post('/v5/api', function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $data = $data['data'] ?? false;

    if ($data && is_array($data)) {
        try {
            $result = AI($data);
            $responseData = [
                'status' => true,
                'data' => $result
            ];
            return $response->withJson($responseData);
        } catch (Exception $e) {
            return $response->withStatus(500)
                ->withJson(['status' => false, 'msg' => 'Internal Server Error']);
        }
    } else {
        return $response->withJson(['status' => false, 'msg' => 'data invalid!']);
    }
});

function AI($arr)
{
    $results = array_map(function ($item) {
        try {
            $imageData = base64_decode($item['img']);
            $image = imagecreatefromstring($imageData);

            // Image processing (brightness, contrast, etc.) can be applied here if needed

            $tempImagePath = tempnam(sys_get_temp_dir(), 'ocr_image_');
            imagejpeg($image, $tempImagePath);

            $tesseract = new TesseractOCR($tempImagePath);
            $tesseract->whitelist(range(0, 9));
            $text = $tesseract->run();

            unlink($tempImagePath);

            return ['id' => $item['id'], 'text' => trim($text)];
        } catch (Exception $e) {
            throw $e;
        }
    }, $arr);

    return $results;
}

$app->run();
