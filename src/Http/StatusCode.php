<?php

namespace Cube\Http;

class StatusCode
{
    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/100
     */
    public const CONTINUE = 100;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/101
     */
    public const SWITCHING_PROTOCOLS = 101;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/102
     */
    public const PROCESSING = 102;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/103
     */
    public const EARLY_HINTS = 103;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/200
     */
    public const OK = 200;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/201
     */
    public const CREATED = 201;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/202
     */
    public const ACCEPTED = 202;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/203
     */
    public const NON_AUTHORITATIVE_INFORMATION = 203;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/204
     */
    public const NO_CONTENT = 204;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/205
     */
    public const RESET_CONTENT = 205;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/206
     */
    public const PARTIAL_CONTENT = 206;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/207
     */
    public const MULTI_STATUS = 207;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/208
     */
    public const ALREADY_REPORTED = 208;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/226
     */
    public const IM_USED = 226;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/300
     */
    public const MULTIPLE_CHOICES = 300;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/301
     */
    public const MOVED_PERMANENTLY = 301;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/302
     */
    public const FOUND = 302;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/303
     */
    public const SEE_OTHER = 303;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/304
     */
    public const NOT_MODIFIED = 304;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/305
     */
    public const USE_PROXY = 305;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/306
     */
    public const UNUSED = 306;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/307
     */
    public const TEMPORARY_REDIRECT = 307;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/308
     */
    public const PERMANENT_REDIRECT = 308;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/400
     */
    public const BAD_REQUEST = 400;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/401
     */
    public const UNAUTHORIZED = 401;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/402
     */
    public const PAYMENT_REQUIRED = 402;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/403
     */
    public const FORBIDDEN = 403;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/404
     */
    public const NOT_FOUND = 404;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/405
     */
    public const METHOD_NOT_ALLOWED = 405;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/406
     */
    public const NOT_ACCEPTABLE = 406;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/407
     */
    public const PROXY_AUTHENTICATION_REQUIRED = 407;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/408
     */
    public const REQUEST_TIMEOUT = 408;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/409
     */
    public const CONFLICT = 409;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/410
     */
    public const GONE = 410;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/411
     */
    public const LENGTH_REQUIRED = 411;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/412
     */
    public const PRECONDITION_FAILED = 412;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/413
     */
    public const CONTENT_TOO_LARGE = 413;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/414
     */
    public const URI_TOO_LONG = 414;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/415
     */
    public const UNSUPPORTED_MEDIA_TYPE = 415;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/416
     */
    public const RANGE_NOT_SATISFIABLE = 416;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/417
     */
    public const EXPECTATION_FAILED = 417;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/418
     */
    public const IM_A_TEAPOT = 418;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/421
     */
    public const MISDIRECTED_REQUEST = 421;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/422
     */
    public const UNPROCESSABLE_CONTENT = 422;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/423
     */
    public const LOCKED = 423;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/424
     */
    public const FAILED_DEPENDENCY = 424;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/425
     */
    public const TOO_EARLY = 425;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/426
     */
    public const UPGRADE_REQUIRED = 426;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/428
     */
    public const PRECONDITION_REQUIRED = 428;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/429
     */
    public const TOO_MANY_REQUESTS = 429;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/431
     */
    public const REQUEST_HEADER_FIELDS_TOO_LARGE = 431;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/451
     */
    public const UNAVAILABLE_FOR_LEGAL_REASONS = 451;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/500
     */
    public const INTERNAL_SERVER_ERROR = 500;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/501
     */
    public const NOT_IMPLEMENTED = 501;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/502
     */
    public const BAD_GATEWAY = 502;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/503
     */
    public const SERVICE_UNAVAILABLE = 503;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/504
     */
    public const GATEWAY_TIMEOUT = 504;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/505
     */
    public const HTTP_VERSION_NOT_SUPPORTED = 505;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/506
     */
    public const VARIANT_ALSO_NEGOTIATES = 506;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/507
     */
    public const INSUFFICIENT_STORAGE = 507;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/508
     */
    public const LOOP_DETECTED = 508;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/510
     */
    public const NOT_EXTENDED = 510;

    /**
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/511
     */
    public const NETWORK_AUTHENTICATION_REQUIRED = 511;
}
