<?php

declare(strict_types=1);

namespace App\Service\Qr;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrService
{
    public function generateQrCode(string $url): string
    {
        $options = new QROptions([
            'eccLevel' => QRCode::ECC_H,
            'scale' => 10,
        ]);

        return (new QRCode($options))->render($url);
    }
}
