<?php

namespace Cube\Web\Http;

/**
 * What the rules of a `Request` subclass validate, as `getValidatedSource()` answers it.
 */
enum RequestSource
{
    /** The query string merged with the parsed body and the uploads, the query string winning a shared key */
    case Merged;

    /** The parsed body as it was sent (an object, a list or a scalar for JSON) with the uploads, without the query string */
    case Body;

    /** The body as a raw string, whatever its content type */
    case RawBody;
}
