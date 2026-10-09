<?php

/** The company details printed on customer-facing PDFs. Anything left empty is simply not printed. */
return [
    'name' => env('COMPANY_NAME', 'Mei Bali'),
    'address' => env('COMPANY_ADDRESS'),
    'email' => env('COMPANY_EMAIL'),
    'phone' => env('COMPANY_PHONE'),
    // Shown on invoices, e.g. "Bank BCA 123-456-7890 a.n. PT Mei Bali Wisata"
    'bank_account' => env('COMPANY_BANK_ACCOUNT'),
];
