<?php

/**
 * Configuration options for Salesforce Oauth settings and REST API defaults.
 */
return [
    /*
     * Options include WebServer, UserPassword, UserPasswordSoap, ClientCredentials and OAuthJWT
     */
    'authentication' => config('services.salesforce.auth_method') ?? env('SF_AUTH_METHOD', 'UserPassword'),

    /*
     * Enter your credentials
     * Username and Password are only necessary for UserPassword & UserPasswordSoap flows.
     * Likewise, callbackURI is only necessary for WebServer flow.
     * OAuthJWT requires a key, username, and private key (SF_CONSUMER_SECRET)
     */
    'credentials' => [
        // Required:
        'consumerKey' => config('services.salesforce.consumer_key') ?? env('SF_CONSUMER_KEY'),
        'consumerSecret' => config('services.salesforce.consumer_secret') ?? env('SF_CONSUMER_SECRET'),
        'callbackURI' => config('services.salesforce.callback_uri') ?? env('SF_CALLBACK_URI'),
        'loginURL' => config('services.salesforce.login_url') ?? env('SF_LOGIN_URL', 'https://login.salesforce.com'),

        // Only required for UserPassword authentication:
        'username' => config('services.salesforce.username') ?? env('SF_USERNAME'),
        // Security token might need to be amended to password unless IP Address is whitelisted
        'password' => config('services.salesforce.password') ?? env('SF_PASSWORD'),
        // Only required for OAuthJWT authentication:
        'privateKey' => '',
    ],

    /*
     * These are optional authentication parameters that can be specified for the WebServer flow.
     * https://help.salesforce.com/apex/HTViewHelpDoc?id=remoteaccess_oauth_web_server_flow.htm&language=en_US
     */
    'parameters' => [
        'display' => '',
        'immediate' => false,
        'state' => '',
        'scope' => '',
        'prompt' => '',
    ],

    /*
     * Default settings for resource requests.
     * Format can be 'json', 'xml' or 'none'
     * Compression can be set to 'gzip' or 'deflate'
     */
    'defaults' => [
        'method' => 'get',
        'format' => 'json',
        'compression' => false,
        'compressionType' => 'gzip',
    ],

    'client' => [
        'http_errors' => true,
        'verify' => (bool) (config('services.salesforce.http_verify') ?? env('SF_HTTP_VERIFY', false)),
    ],

    /*
     * Where do you want to store access tokens fetched from Salesforce. The type of storage will persist
     * Salesforce token when user refreshes the page. If you choose 'object', the token is stored on the object
     * instance and will persist as long as the object remains in memory.
     */
    'storage' => [
        'type' => 'cache', // Options include: 'session', 'cache', 'object', or class instance of Omniphx\Forrest\Interfaces\StorageInterface
        'path' => 'forrest_', // unique storage path to avoid collisions
        'expire_in' => 3600, // number of seconds to expire cache/session
        'store_forever' => false, // never expire cache/session
    ],

    /*
     * If you'd like to specify an API version manually it can be done here.
     * Format looks like '32.0'
     */
    'version' => config('services.salesforce.api_version') ?? env('SF_API_VERSION', ''),

    /*
     * Optional (and not recommended) if you need to override the instance_url returned from Salesforce
     *
     * This is useful for configuring lightning or lightning sandboxes with OAuthJWT:
     * Lightning: https://<YOUR_ORG>.my.salesforce.com
     * Lightning Sandbox: https://<YOUR_ORG>--<SANDBOX_NAME>.sandbox.my.salesforce.com
     * Developer Org: https://<DEV_DOMAIN>.develop.my.salesforce.com
     */
    'instanceURL' => config('services.salesforce.instance_url') ?? env('SF_INSTANCE_URL', ''),

    /*
     * Language
     */
    'language' => config('services.salesforce.locale') ?? env('SF_LOCALE', 'en_US'),
];
