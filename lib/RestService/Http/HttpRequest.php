<?php

/**
* Copyright 2013 François Kooman <fkooman@tuxed.net>
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
*/

namespace RestService\Http;

class HttpRequest
{
    protected $_uri;
    protected $_method;
    protected $_headers;
    protected $_content;
    protected $_pathInfo;
    protected $_patternMatch;
    protected $_methodMatch;
    protected $_basicAuthUser;
    protected $_basicAuthPass;

    public function __construct($requestUri, $requestMethod = "GET")
    {
        $this->setRequestUri(new Uri($requestUri));
        $this->setRequestMethod($requestMethod);
        $this->_headers = array();
        $this->_content = NULL;
        $this->_pathInfo = NULL;
        $this->_patternMatch = FALSE;
        $this->_methodMatch = array();
        $this->_basicAuthUser = NULL;
        $this->_basicAuthPass = NULL;
    }

    public static function fromIncomingHttpRequest(IncomingHttpRequest $i)
    {
        $request = new static($i->getRequestUri(), $i->getRequestMethod());
        $request->setHeaders($i->getRequestHeaders());
        $request->setContent($i->getContent());
        $request->setPathInfo($i->getPathInfo());
        $request->setBasicAuthUser($i->getBasicAuthUser());
        $request->setBasicAuthPass($i->getBasicAuthPass());

        return $request;
    }

    public function setRequestUri(Uri $u)
    {
        $this->_uri = $u;
    }

    public function getRequestUri()
    {
        return $this->_uri;
    }

    public function setRequestMethod($method)
    {
        if (!in_array($method, array("GET", "POST", "PUT", "DELETE", "HEAD", "OPTIONS"))) {
            throw new HttpRequestException("invalid or unsupported request method");
        }
        $this->_method = $method;
    }

    public function getRequestMethod()
    {
        return $this->_method;
    }

    public function setPostParameters(array $parameters)
    {
        if ($this->getRequestMethod() !== "POST") {
            throw new HttpRequestException("request method should be POST");
        }
        $this->setHeader("Content-Type", "application/x-www-form-urlencoded");
        $this->setContent(http_build_query($parameters, null, "&"));
    }

    public function getQueryParameters()
    {
        if ($this->_uri->getQuery() === NULL) {
            return array();
        }
        $parameters = array();
        parse_str($this->_uri->getQuery(), $parameters);

        return $parameters;
    }

    public function getQueryParameter($key)
    {
        $parameters = $this->getQueryParameters();

        return (array_key_exists($key, $parameters) && 0 !== strlen($parameters[$key])) ? $parameters[$key] : NULL;
    }

    public function getPostParameter($key)
    {
        $parameters = $this->getPostParameters();

        return (array_key_exists($key, $parameters) && 0 !== strlen($parameters[$key])) ? $parameters[$key] : NULL;
    }

    public function getPostParameters()
    {
        if ($this->getRequestMethod() !== "POST") {
            throw new HttpRequestException("request method should be POST");
        }
        $parameters = array();
        parse_str($this->getContent(), $parameters);

        return $parameters;
    }

    public function setHeaders(array $headers)
    {
        foreach ($headers as $k => $v) {
            $this->setHeader($k, $v);
        }
    }

    public function setHeader($key, $value)
    {
        $k = self::normalizeHeaderKey($key);
        $this->_headers[$k] = $value;
    }

    public function getHeader($key)
    {
        $k = self::normalizeHeaderKey($key);

        return array_key_exists($k, $this->_headers) ? $this->_headers[$k] : NULL;
    }

    public function getHeaders($formatted = FALSE)
    {
        if (!$formatted) {
            return $this->_headers;
        }
        $hdrs = array();
        foreach ($this->_headers as $k => $v) {
            array_push($hdrs, $k . ": " . $v);
        }

        return $hdrs;
    }

    public function setContent($content)
    {
        $this->_content = $content;
    }

    public function getContent()
    {
        return $this->_content;
    }

    public function setContentType($contentType)
    {
        $this->setHeader("Content-Type", $contentType);
    }

    public function getContentType()
    {
        return $this->getHeader("Content-Type");
    }

    public function setPathInfo($pathInfo)
    {
        $this->_pathInfo = $pathInfo;
    }

    public function getPathInfo()
    {
        return $this->_pathInfo;
    }

    public function setBasicAuthUser($u)
    {
        $this->_basicAuthUser = $u;
    }

    public function setBasicAuthPass($p)
    {
        $this->_basicAuthPass = $p;
    }

    public function getBasicAuthUser()
    {
        return $this->_basicAuthUser;
    }

    public function getBasicAuthPass()
    {
        return $this->_basicAuthPass;
    }

    public function matchRest($requestMethod, $requestPattern, $callback)
    {
        // we already matched something before...ignore this one
        if (TRUE === $this->_patternMatch) {
            return FALSE;
        }

        // record the method so it can be used to construct the "Allow" header
        // if no pattern matches the request
        if (!in_array($requestMethod, $this->_methodMatch)) {
            array_push($this->_methodMatch, $requestMethod);
        }
        if ($requestMethod !== $this->getRequestMethod()) {
            return FALSE;
        }
        // if no pattern is defined, all paths are valid
        if (NULL === $requestPattern) {
            $this->_patternMatch = TRUE;

            return TRUE;
        }
        // both the pattern and request path should start with a "/"
        if (0 !== strpos($this->getPathInfo(), "/") || 0 !== strpos($requestPattern, "/")) {
            return FALSE;
        }

        // handle optional parameters
        $requestPattern = str_replace(')', ')?', $requestPattern);

        // check for variables in the requestPattern
        $pma = preg_match_all('#:([\w]+)\+?#', $requestPattern, $matches);
        if (FALSE === $pma) {
            throw new HttpRequestException("regex for variable search failed");
        }
        if (0 === $pma) {
            // no matches found, so no variables in the pattern, pattern and request must be identical
            if ($this->getPathInfo() === $requestPattern) {
                $this->_patternMatch = TRUE;
                call_user_func_array($callback, array());

                return TRUE;
            }
        }
        // replace all the variables with a regex so the actual value in the request
        // can be captured
        foreach ($matches[0] as $m) {
            // determine pattern based on whether variable is wildcard or not
            $mm = str_replace(array(":", "+"), "", $m);
            $pattern = (strpos($m, "+") === strlen($m) -1) ? '(?P<' . $mm . '>(.+?[^/]))' : '(?P<' . $mm . '>([^/]+))';
            $requestPattern = str_replace($m, $pattern, $requestPattern);
        }
        $pm = preg_match("#^" . $requestPattern . "$#", $this->getPathInfo(), $parameters);
        if (FALSE === $pm) {
            throw new HttpRequestException("regex for path matching failed");
        }
        if (0 === $pm) {
            // request path does not match pattern
            return FALSE;
        }
        foreach ($parameters as $k => $v) {
            if (!is_string($k)) {
                unset($parameters[$k]);
            }
        }
        // request path matches pattern!
        $this->_patternMatch = TRUE;
        call_user_func_array($callback, array_values($parameters));

        return TRUE;
    }

    public function matchRestDefault($callback)
    {
        $callback($this->_methodMatch, $this->_patternMatch);
    }

    public static function normalizeHeaderKey($key)
    {
        // strip HTTP_ if needed
        if (0 === strpos($key, 'HTTP_') || 0 === strpos($key, 'HTTP-')) {
            $key = substr($key, 5);
        }
        // convert to capitals and replace '-' with '_'
        return strtoupper(str_replace('-', '_', $key));
    }

    public function __toString()
    {
        $s  = PHP_EOL;
        $s .= "*HttpRequest*" . PHP_EOL;
        $s .= "Request Method: " . $this->getRequestMethod() . PHP_EOL;
        $s .= "Request URI: " . $this->getRequestUri()->getUri() . PHP_EOL;
        if (NULL !== $this->getBasicAuthUser()) {
            $s .= "Basic Authentication: " . $this->getBasicAuthUser() . ":" . $this->getBasicAuthPass();
        }
        $s .= "Headers:" . PHP_EOL;
        foreach ($this->getHeaders(TRUE) as $v) {
            $s .= "\t" . $v . PHP_EOL;
        }
        $s .= "Content:" . PHP_EOL;
        $s .= $this->getContent();

        return $s;
    }

}
