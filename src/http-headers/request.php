<?php
/**
 * Copyright 2015 Joshua Johnston.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  * Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 *
 *  * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 *  * Neither the name of the Avant Web Consulting nor the names of its contributors
 *   may be used to endorse or promote products derived from this software without specific
 *   prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   HTTPHeaders
 * @package    HTTPHeaders
 * @subpackage Request
 * @author     Joshua Johnston <johnston.joshua@gmail.com>
 * @copyright  2015 Joshua Johnston
 * @license    http://opensource.org/licenses/BSD-3-Clause BSD 3 Clause
 * @link       http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html RFC 2616 Section 14
 */

namespace Trii\HTTPHeaders;

/**
 * Interface for all request headers listed in rfc 2616 sec 14
 */
interface IHeader {

    /**
     * Parses a string based upon the RFC spec of this HTTP Header.
     *
     * @param string $header HTTP header value after the colon
     */
    public function parse($header);

    /**
     * Returns the literal value the header stores.
     *
     * @return string
     */
    public function getValue();

    /**
     * Returns the name of this header for building valid output.
     *
     * Example:
     * ```php
     * <?php
     * $ifmatch = new IfMatch();
     * echo $ifmatch->getName();
     * // prints If-Match
     * ?>
     * ```
     *
     * @return string
     */
    public function getName();

    /**
     * String context implementation.
     *
     * This should return a valid HTTP header string
     *
     * Example:
     * <code>
     * echo $header;
     * // Accept: text/plain; q=0.5, text/html, application/xml; q=0.8
     * </code>
     */
    public function __toString();
}

/**
 * Adds default toString() implementation
 */
abstract class Header implements IHeader {

    /**
     * @var string
     */
    protected $value;

    /**
     *
     * @param string $header_string Optional value to initialize
     */
    public function __construct($header_string = null) {
        if (!is_null($header_string)) {
            $this->parse($header_string);
        }
    }

    /**
     * Default impl.
     *
     * @see Header::setValue()
     * @param string $header
     */
    public function parse($header) {
        $this->setValue($header);
    }

    /**
     * Gets the header value passed
     * @return string
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * Gets the header value as originally set
     * @param string $value
     */
    public function setValue($value) {
        $this->value = $value;
    }

    /**
     * Returns the raw, pre-parsed header value
     */
    public function __toString() {
        return sprintf('%s: %s', $this->getName(), $this->getValue());
    }
}

/**
 * Mime Types that the user agent will accept.
 *
 * An example header would be
 * <code>Accept: text/html,application/xhtml+xml,application/xml;q=0.9,* /*;q=0.8,application/json;q=0.0</code>
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
 */
class Accept extends Header implements \IteratorAggregate {

    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'Accept';
    }

        /**
     * Sorted list of mime-types the client will accept
     *
     * @var array
     */
    protected $mimeTypes = [];

    /**
     * Parses the HTTP Accept header
     *
     * Code inspired by Wez Furlong
     * @link http://shiflett.org/blog/2011/may/the-accept-header
     * @link http://shiflett.org/blog/2011/may/the-accept-header#comment-7
     *
     * @param string $header the value of the Accept header after the colon
     */
    public function parse($header) {
        $this->setValue($header);

        $this->mimeTypes = [];

        $accept = [];

        foreach (preg_split('/\,\s*/', $header) as $i => $term) {

            $mime = [];

            $mime['pos'] = $i;

            if (preg_match(",^(\S+)(;.+),i", $term, $M)) {
                $mime['type'] = $M[1];
                // parse_str is magic like a query string
                parse_str(str_replace(';', '&', $M[2]), $p);
                foreach ($p as $k => $v) {
                    $mime[$k] = $v;
                    if ($k != 'q') {
                        // put any extensions back
                        $mime['type'] .= ";$k=$v";
                    }
                }

                if (!isset($mime['q'])) {
                    $mime['q'] = 1;
                }
            } else {

                $mime['type'] = $term;
                $mime['q'] = 1;
            }

            $mime['q'] = (double) $mime['q'];

            $accept[] = $mime;
        }

        // weighted sort
        usort($accept, function ($a, $b) {
            // normal sort by quality
            if ($b['q'] != $a['q']) {
                return $b['q'] > $a['q'] ? 1 : -1;
            }

            // matching quality goes by most specific then finally
            // by order
            // Media ranges can be overridden by more specific media ranges
            // or specific media types. If more than one media range
            // applies to a given type, the most specific reference has
            // precedence.
            list($a['t'], $a['s']) = explode('/', $a['type']);
            list($b['t'], $b['s']) = explode('/', $b['type']);

            // not the same type, order by position
            if ($a['t'] != $b['t']) {
                return $a['pos'] - $b['pos'];
            }

            // wildcards are lower priority
            if ($a['s'] == '*') {
                return 1;
            }

            if ($b['s'] == '*') {
                return -1;
            }

            // remove extension to see if subtype is the same
            list($a['st']) = explode(';', $a['s']);
            list($b['st']) = explode(';', $b['s']);

            // same type, different subtype
            if ($a['t'] == $b['t'] && $a['st'] != $b['st']) {
                return $a['pos'] - $b['pos'];
            }

            // more specific extension?
            if (count($b) == count($a)) {
                return $a['pos'] - $b['pos'];
            }

            return count($b) - count($a);
        });

        foreach ($accept as $a) {
            $this->mimeTypes[strtolower($a['type'])] = $a['type'];
        }
    }

    /**
     * Gets all mime types that this user agent accepts
     *
     * @return array
     */
    public function getTypes() {
        return $this->mimeTypes;
    }

    /**
     * Gets the preferred mime type of the user-agent. Usually the first item
     * listed in the header unless all mimes are weighted
     *
     * @return string
     */
    public function getPreferredType() {
        reset($this->mimeTypes);
        return $this->mimeTypes[key($this->mimeTypes)];
    }

    /**
     * Does the User-Agent accept this mime type?
     *
     * If the agent has a wildcard in accept * /* this will return true
     *
     * @todo Handle wildcard mimes like audio/*
     *
     * @param string $mimeType the mime type to check
     *
     * @return boolean
     */
    public function isAccepted($mimeType) {
        $mimeType = strtolower($mimeType);

        // straight match
        if (isset($this->mimeTypes[$mimeType])) {
            return true;
        }

        // match against major type wildcard
        list($type, $subType) = explode('/', $mimeType);

        if (isset($this->mimeTypes["$type/*"])) {
            return true;
        }

        // match against generic wildcard
        if (isset($this->mimeTypes['*/*'])) {
            return true;
        }

        return false;
    }

    /**
     * Implement IteratorAggregate
     *
     * @return \ArrayIterator
     */
    public function getIterator() {
        return new \ArrayIterator($this->mimeTypes);
    }

}

/**
 * Character Sets that the user agent accepts.
 *
 * An example header would be
 * <code>Accept-Charset: iso-8859-5, unicode-1-1;q=0.8</code>
 *
 * The special value "*", if present in the Accept-Charset field, matches every
 * character set (including ISO-8859-1) which is not mentioned elsewhere in the
 * Accept-Charset field. If no "*" is present in an Accept-Charset field, then
 * all character sets not explicitly mentioned get a quality value of 0, except
 * for ISO-8859-1, which gets a quality value of 1 if not explicitly mentioned.
 *
 * If no Accept-Charset header is present, the default is that any character
 * set is acceptable. If an Accept-Charset header is present, and if the server
 * cannot send a response which is acceptable according to the Accept-Charset
 * header, then the server SHOULD send an error response with the 406
 * (not acceptable) status code, though the sending of an unacceptable response
 * is also allowed.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.2
 */
class AcceptCharset extends Header implements \IteratorAggregate {

    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'Accept-Charset';
    }

    /**
     * Sorted list of charsets the client will accept
     *
     * @var array
     */
    protected $charsets = [];

    /**
     * Parses the HTTP Accept-Charset header
     *
     * @see \Trii\HTTPHeaders\Accept::parse
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
     *
     * @param string $header the value of the Accept-Charset header after the colon
     */
    public function parse($header) {
        $this->setValue($header);

        $this->charsets = [];

        $accept = [];

        $hasISO88591 = false;

        foreach (preg_split('/\s*,\s*/', $header) as $i => $term) {

            $o = new \stdclass;

            $o->pos = $i;

            if (preg_match(",^(\S+)\s*;\s*q=([0-9\.]+),i", $term, $M)) {

                $o->type = $M[1];
                $o->q = (double) $M[2];
            } else {

                $o->type = $term;
                $o->q = 1;
            }

            if (strtolower($o->type) == 'iso-8859-1' || $o->type == '*') {
                $hasISO88591 = true;
            }

            $accept[] = $o;
        }

        // see note on ISO-8859-1 and * in class comment
        // insert iso-8859-1 as the last item before q<1
        if (!$hasISO88591) {
            $o = new \stdclass;
            $o->type = 'iso-8859-1';
            $o->q = 1;
            $o->pos = count($accept);
            $accept[] = $o;
        }

        // weighted sort
        usort($accept, function ($a, $b) {

            // first tier: highest q factor wins
            $diff = $b->q - $a->q;

            if ($diff > 0) {
                return 1;
            } elseif ($diff < 0) {
                return -1;
            } else {
                // tie-breaker: first listed item wins
                return $a->pos - $b->pos;
            }
        });

        foreach ($accept as $a) {

            $a->type = strtolower($a->type);
            $this->charsets[$a->type] = $a;
        }
    }

    /**
     * Gets all charsets that this user agent accepts
     *
     * @return array
     */
    public function getCharsets() {
        return array_keys($this->charsets);
    }

    /**
     * Gets the preferred charset of the user-agent. Usually the first item
     * listed in the header unless all charsets are weighted
     *
     * @return string
     */
    public function getPreferredCharset() {
        return $this->charsets[key($this->charsets)]->type;
    }

    /**
     * Does the User-Agent accept this charset?
     *
     * If the agent has a wildcard (*) or no Accept-Charset header this will
     * return true per the RFC
     *
     * @param string $charset the charset to check
     *
     * @return boolean
     */
    public function isAccepted($charset) {
        if (count($this->charsets) == 0) {
            return true;
        }

        // set and q>0 is ok, but q=0 means do not use
        if (isset($this->charsets[strtolower($charset)])) {
            return ($this->charsets[strtolower($charset)]->q > 0);
        }

        return (isset($this->charsets['*']));
    }

    /**
     * Implement IteratorAggregate
     *
     * @return \ArrayIterator
     */
    public function getIterator() {
        return new \ArrayIterator($this->getCharsets());
    }

}

/**
 * Content encodings that the user accept finds acceptable.
 *
 * An example header would be
 * <code>
 * Accept-Encoding: compress, gzip
 * Accept-Encoding:
 * Accept-Encoding: *
 * Accept-Encoding: compress;q=0.5, gzip;q=1.0
 * Accept-Encoding: gzip;q=1.0, identity; q=0.5, *;q=0
 * </code>
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.3
 */
class AcceptEncoding extends Header {

    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'Accept-Encoding';
    }

    /**
     * Sorted list of content-codings the client will accept
     *
     * @var array
     */
    protected $encoding = [];

    /**
     * Parses the HTTP Accept-Encoding header
     *
     * @see \Trii\HTTPHeaders\Accept::parse
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
     *
     * @param string $header the value of the Accept-Encoding header after the colon. An empty header implies identity only
     */
    public function parse($header = 'identity') {
        $this->resetEncodingQualityValues();

        foreach (preg_split('/\s*,\s*/', $header) as $i => $term) {

            $o = new \stdclass;

            $o->pos = $i;

            if (preg_match(",^(\S+)\s*;\s*q=([0-9\.]+),i", $term, $M)) {

                $o->type = strtolower($M[1]);
                $o->q = (double) $M[2];
            } else {

                $o->type = strtolower($term);
                $o->q = 1;
            }

            switch ($o->type) {

                case 'x-gzip':
                    $this->encoding['gzip'] = $o->q;
                    break;

                case 'x-compress':
                    $this->encoding['compress'] = $o->q;
                    break;

                case '*':
                    foreach ($this->encoding as $enc => $q) {
                        if (null === $this->encoding[$enc]) {
                            $this->encoding[$enc] = $o->q;
                        }
                    }
                    break;

                default:
                    $this->encoding[$o->type] = $o->q;
                    break;
            }
        }

        // anything left as null means it is not accepted
        // so we set it to 0.0 for sort / isAccepted
        foreach ($this->encoding as $enc => $q) {
            if (null === $this->encoding[$enc]) {
                // The "identity" content-coding is always acceptable, unless
                // specifically refused because the Accept-Encoding field includes
                // "identity;q=0", or because the field includes "*;q=0" and does
                // not explicitly include the "identity" content-coding.
                if ($enc === 'identity') {
                    $this->encoding[$enc] = 1.0;
                } else {
                    $this->encoding[$enc] = 0.0;
                }
            }
        }

        arsort($this->encoding);
    }

    /**
     * Gets all content-codings that this user agent accepts
     *
     * @return array
     */
    public function getEncodings() {
        return $this->encoding;
    }

    /**
     * Gets the preferred content-codings of the user-agent. Usually the first item
     * listed in the header unless all content-codings are weighted
     *
     * @return string
     */
    public function getPreferredEncoding() {
        if (!$this->encoding) {
            return 'identity';
        }
        reset($this->encoding);
        $k = key($this->encoding);

        if ($this->encoding[$k] > 0) {
            return $k;
        }

        return null;
    }

    /**
     * Does the User-Agent accept this content-codings?
     *
     * The "identity" content-coding is always acceptable, unless
     * specifically refused because the Accept-Encoding field includes
     * "identity;q=0", or because the field includes "*;q=0" and does
     * not explicitly include the "identity" content-coding. If the
     * Accept-Encoding field-value is empty, then only the "identity"
     * encoding is acceptable.
     *
     * @param string $encoding the content-codings to check
     *
     * @return boolean
     */
    public function isAccepted($encoding) {
        $k = strtolower($encoding);

        // set and a non-zero quality means yes
        if (isset($this->encoding[$k])) {
            return (bool) $this->encoding[$k];
        }

        // any unknown encoding is treated as an identity
        return (bool) $this->encoding['identity'];
    }

    /**
     * Resets the qvalue for each accepted encoding
     */
    protected function resetEncodingQualityValues() {
        $this->encoding = [
            'gzip' => null,
            'compress' => null,
            'deflate' => null,
            'identity' => null
        ];
    }

}

/**
 * Language(s) that the user-agent accepts.
 *
 * The Accept-Language request-header field is similar to Accept, but restricts
 * the set of natural languages that are preferred as a response to the request.
 *
 * An example header would be
 * <code>
 * Accept-Language: da, en-gb;q=0.8, en;q=0.7
 * </code>
 * Which means: "I prefer Danish, but will accept British English and other
 * types of English." A language-range matches a language-tag if it exactly
 * equals the tag, or if it exactly equals a prefix of the tag such that the
 * first tag character following the prefix is "-". The special range "*", if
 * present in the Accept-Language field, matches every tag not matched by any
 * other range present in the Accept-Language field.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4
 */
class AcceptLanguage extends Header {

    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'Accept-Language';
    }

    /**
     * Sorted list of languages the client will accept
     *
     * @var array
     */
    protected $language = [];

    /**
     * Parses the HTTP Accept-Language header
     *
     * @see \Trii\HTTPHeaders\Accept::parse
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
     *
     * @param string $header the value of the Accept-Language header after the colon
     */
    public function parse($header) {
        $this->setValue($header);

        $this->language = [];

        $this->accept = [];

        foreach (preg_split('/\s*,\s*/', $header) as $i => $term) {

            $o = new \stdclass;

            $o->pos = $i;

            if (preg_match(",^(\S+)\s*;\s*q=([0-9\.]+),i", $term, $M)) {

                $o->type = $M[1];
                $o->q = (double) $M[2];
            } else {

                $o->type = $term;
                $o->q = 1;
            }

            $this->accept[strtolower($o->type)] = $o;
        }

        // weighted sort
        uasort($this->accept, function ($a, $b) {

            // first tier: highest q factor wins
            $diff = $b->q - $a->q;

            if ($diff > 0) {

                $diff = 1;
            } else if ($diff < 0) {

                $diff = -1;
            } else {

                // tie-breaker: first listed item wins
                $diff = $a->pos - $b->pos;
            }

            return $diff;
        });

        foreach ($this->accept as $a) {

            $this->language[strtolower($a->type)] = $a->type;
        }
    }

    /**
     * Gets all languages that this user agent accepts
     *
     * @return array
     */
    public function getLanguages() {
        return $this->language;
    }

    /**
     * Gets the preferred langauges of the user-agent. Usually the first item
     * listed in the header unless all languages are weighted
     *
     * @return string
     */
    public function getPreferredLanguage() {
        if (count($this->language) == 0) {
            return '*';
        }

        foreach ($this->language as $k => $lang) {
            if ($this->accept[$k]->q) {
                return $lang;
            }
        }

        return null;
    }

    /**
     * Does the User-Agent accept this language?
     *
     * @param string $language the language to check
     *
     * @return boolean
     */
    public function isAccepted($language) {
        $k = strtolower($language);

        // set and a non-zero quality
        if (isset($this->language[$k])) {
            return (bool) $this->accept[$k]->q;
        }

        // handle the acceptance of language without range
        if (strpos('-', $k)) {

            list($type) = explode('-', $k, 1);

            if (isset($this->language[$type])) {
                return (bool) $this->accept[$type]->q;
            }
        }

        // or wildcard and a non-zero quality
        if (isset($this->language['*'])) {
            return (bool) $this->accept['*']->q;
        }

        return false;
    }

}

/**
 * Authorization value presented by the user agent.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.8
 */
class Authorization extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'Authorization';
    }
}

/**
 * The Expect request-header field is used to indicate that particular server behaviors are required by the client.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.20
 */
class Expect extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'Expect';
    }
}

/**
 * HTTP From Request header
 *
 * The From request-header field, if given, SHOULD contain an Internet e-mail
 * address for the human user who controls the requesting user agent
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.22
 */
class From extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'From';
    }
}

/**
 * HTTP Host Request header
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.23
 */
class Host extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'Host';
    }
}

/**
 * The If-Match request-header field is used with a method to make it
 * conditional.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.24
 */
class IfMatch extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'If-Match';
    }
}

/**
 * The If-Modified-Since request-header field is used with a method to make it
 * conditional: if the requested variant has not been modified since the time
 * specified in this field, an entity will not be returned from the server;
 * instead, a 304 (not modified) response will be returned without any
 * message-body.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.25
 */
class IfModifiedSince extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'If-Modified-Since';
    }
}

/**
 * The If-None-Match request-header field is used with a method to make it
 * conditional. A client that has one or more entities previously obtained
 * from the resource can verify that none of those entities is current by
 * including a list of their associated entity tags in the If-None-Match header
 * field. The purpose of this feature is to allow efficient updates of cached
 * information with a minimum amount of transaction overhead. It is also used
 * to prevent a method (e.g. PUT) from inadvertently modifying an existing
 * resource when the client believes that the resource does not exist.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.26
 */
class IfNoneMatch extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'If-None-Match';
    }
}

/**
 * If a client has a partial copy of an entity in its cache, and wishes to have
 * an up-to-date copy of the entire entity in its cache, it could use the Range
 * request-header with a conditional GET (using either or both of
 * If-Unmodified-Since and If-Match.) However, if the condition fails because
 * the entity has been modified, the client would then have to make a second
 * request to obtain the entire current entity-body.
 *
 * The If-Range header allows a client to "short-circuit" the second request.
 * Informally, its meaning is `if the entity is unchanged, send me the part(s)
 * that I am missing; otherwise, send me the entire new entity'.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.27
 */
class IfRange extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'If-Range';
    }
}

/**
 * The If-Unmodified-Since request-header field is used with a method to make it
 * conditional. If the requested resource has not been modified since the time
 * specified in this field, the server SHOULD perform the requested operation
 * as if the If-Unmodified-Since header were not present.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.27
 */
class IfUnmodifiedSince extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'If-Unmodified-Since';
    }
}

/**
 * The Max-Forwards request-header field provides a mechanism with the TRACE
 * (section 9.8) and OPTIONS (section 9.2) methods to limit the number of
 * proxies or gateways that can forward the request to the next inbound server.
 * This can be useful when the client is attempting to trace a request chain
 * which appears to be failing or looping in mid-chain.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.31
 */
class MaxForwards extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'Max-Forwards';
    }
}

/**
 * The Proxy-Authorization request-header field allows the client to identify
 * itself (or its user) to a proxy which requires authentication. The
 * Proxy-Authorization field value consists of credentials containing the
 * authentication information of the user agent for the proxy and/or realm of
 * the resource being requested.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.33
 */
class ProxyAuthorization extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'Proxy-Authorization';
    }
}

/**
 * Specifies the range of data that the user agent is requesting
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.35
 */
class Range extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'Range';
    }
}

/**
 * The Referer[sic] request-header field allows the client to specify, for the
 * server's benefit, the address (URI) of the resource from which the
 * Request-URI was obtained (the "referrer", although the header field is
 * misspelled.) The Referer request-header allows a server to generate lists of
 * back-links to resources for interest, logging, optimized caching, etc. It
 * also allows obsolete or mistyped links to be traced for maintenance. The
 * Referer field MUST NOT be sent if the Request-URI was obtained from a source
 * that does not have its own URI, such as input from the user keyboard.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.36
 */
class Referer extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'Referer';
    }
}

/**
 * The TE request-header field indicates what extension transfer-codings it is
 * willing to accept in the response and whether or not it is willing to accept
 * trailer fields in a chunked transfer-coding. Its value may consist of the
 * keyword "trailers" and/or a comma-separated list of extension transfer-coding
 * names with optional accept parameters (as described in section 3.6).
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.39
 */
class Te extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'TE';
    }
}

/**
 * The User-Agent request-header field contains information about the user agent
 *  originating the request. This is for statistical purposes, the tracing of
 * protocol violations, and automated recognition of user agents for the sake of
 * tailoring responses to avoid particular user agent limitations. User agents
 * SHOULD include this field with requests. The field can contain multiple
 * product tokens (section 3.8) and comments identifying the agent and any
 * subproducts which form a significant part of the user agent. By convention,
 * the product tokens are listed in order of their significance for identifying
 * the application.
 *
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.43
 */
class UserAgent extends Header {
    /**
     * Header name
     * @return string
     */
    public function getName() {
        return 'User-Agent';
    }
}
