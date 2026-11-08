<?php
return [
    'base_url'  => env('MISA_AIO_URL'),
    'appid'     => env('MISA_APP_ID'),
    'tax_code'   => env('MISA_TAX_CODE'),
    'username'  => env('MISA_USERNAME'),
    'password'  => env('MISA_PASSWORD'),

    // series mặc định (đổi theo template của bạn)
    'default_inv_series' => env('MISA_INV_SERIES', '5C25GYY'),
    // ví dụ 2 mẫu vé bạn đã có:
    'ticket_series_fixed'    => env('MISA_TICKET_SERIES_FIXED', '5C25GYY'),
    'ticket_series_variable' => env('MISA_TICKET_SERIES_VARIABLE', '5C25MYY'),

    'vat_rate'  => (float) env('INVOICE_VAT_RATE', 0.10),
    'send_email_after_publish' => (bool) env('MISA_SEND_EMAIL', false),

    'currency_code'  => env('MISA_CURRENCY', 'VND'),
    'exchange_rate'  => (float) env('MISA_EXCHANGE_RATE', 1),

    // Số chữ số thập phân hiển thị/tính (MISA yêu cầu dạng string)
    'decimals' => [
        'QuantityDecimalDigits'      => env('MISA_DEC_QTY', '0'),
        'UnitPriceOCDecimalDigits'   => env('MISA_DEC_UNITPRICE_OC', '0'),
        'AmountOCDecimalDigits'      => env('MISA_DEC_AMOUNT_OC', '0'),
        'AmountDecimalDigits'        => env('MISA_DEC_AMOUNT', '0'),
        'CoefficientDecimalDigits'   => env('MISA_DEC_COEFF', '2'),
        'ExchangRateDecimalDigits'   => env('MISA_DEC_EXRATE', '0'),
    ],
];
