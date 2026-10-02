<?php
/**
 * Copy this file to contact-config.php on the server and fill it in.
 * contact-config.php is git-ignored and blocked from web access by .htaccess.
 */
return [
    // Where contact-form messages are delivered.
    'to' => 'lara@lp-consultora.com',

    // Sender shown in the mail. Use a real mailbox on this domain (SPF/DKIM
    // alignment is what keeps these out of spam). Same as 'to' is fine: the
    // visitor's address goes in Reply-To, so hitting "Reply" answers them.
    'from' => 'lara@lp-consultora.com',
    'from_name' => 'Web LP Consultora',

    // Hosts allowed to POST to contact.php (checked against the Origin header).
    'allowed_hosts' => ['lp-consultora.com', 'www.lp-consultora.com'],

    // SMTP login for the mailbox above. 'user' => '' (default here) sends through the hosting's own
    // mail via PHP mail(): GoDaddy cPanel hosting blocks outbound SMTP to external servers (including
    // smtpout.secureserver.net), so authenticated SMTP only works on hosts that allow it.
    // This site's mailbox is GoDaddy Professional Email (Titan): smtpout.secureserver.net, 465/ssl.
    // (A cPanel mailbox would use mail.<domain> instead; see README.)
    'smtp' => [
        'host' => 'smtpout.secureserver.net',
        'port' => 465,
        'secure' => 'ssl', // 'ssl' (port 465) or 'tls' (port 587)
        'user' => '',
        'pass' => '',
    ],

    // Set to true to log the SMTP conversation to error_log while troubleshooting. Turn off after.
    'debug' => false,

    // Max submissions per IP per window (seconds).
    'rate_limit' => ['max' => 5, 'window' => 3600],
];
