<?php

namespace Web64\Nlp;

/**
 * 		Simple interface to the Web64 NLP-Server for Natural Language Processing tasks
 */

class NlpClient{
	
	public $api_url;
    public $api_hosts = [];
    public $fail_count = 0;
    public $debug = false;
    private $max_retry_count = 3;
	
	function __construct( $hosts, $debug = false )
	{
		$this->debug = (bool)$debug;

        if ( is_array($hosts) )
        {
			foreach( $hosts as $host )
				$this->addHost( $host );
		}
        else
            $this->addHost( $hosts );
		

		// pick random host as default
		$this->api_url = $this->api_hosts[
			array_rand( $this->api_hosts )
		]; 
    }

    public function addHost( $host )
    {
		$host = rtrim( $host , '/');

        if (  array_search($host, $this->api_hosts) === false)
            $this->api_hosts[] = $host;
    }
    
    // debug message
    private function msg( $value )
    {
        if ( $this->debug )
        {
            if ( is_array($value) )
            {
                print_r( $value );
                echo PHP_EOL;
            }
            else
                echo $value . PHP_EOL;
        }
    }

	// find working host
	private function chooseHost()
	{
		$random_a = $this->api_hosts;
		shuffle($random_a); // pick random host
		
		foreach( $random_a as $api_url )
		{
            $this->msg( "chooseHost() - Testing: $api_url ");
            
			$content = @file_get_contents( $api_url );
			if ( empty( $content ) )
			{

                $this->msg( $content );
				// Failed
                $this->msg( "- Ignoring failed API URL: $api_url " );
				//print_r( $http_response_header );
			}else{
				$this->api_url = $api_url;
				$this->msg( "- Working API URL: $api_url" );
				return true;
				 
			}
            $this->msg( $content );
		}
		
		return false;
	}
	
	public function newspaperHtml( $html )
	{
		$data =  $this->post_call('/newspaper', ['text' => $html ] );
		
		return ( !empty($data['newspaper']) ) ? $data['newspaper'] : null;
	}

	public function newspaperUrl( $url )
	{
		$data = $this->get_call('/newspaper', ['url' => $url ] );

		return ( !empty($data['newspaper']) ) ? $data['newspaper'] : null;
	}


	public function embeddings( $word, $lang = 'en')
	{
		$data = $this->get_call('/embeddings', ['word' => $word, 'lang' => $lang ] );

		return ( !empty($data['neighbours']) ) ? $data['neighbours'] : null;
	}

	/**
	 * 		Get entities and sentiment analysis of text
	 */
	public function polyglot( $text, $language = null )
	{
		$data = $this->post_call('/polyglot', ['text' => $text, 'lang' => $language] );
		
		return new \Web64\Nlp\Classes\PolyglotResponse( $data['polyglot'] );
	}

	/**
	 * 		Get language code for text
	 */
	public function language( $text )
	{
		$data = $this->post_call('/language', ['text' => $text] );

		if ( isset($data['langid']) && isset($data['langid']['language']))
		{
			// return 'no' for Norwegian Bokmaal and Nynorsk
			if ( $data['langid']['language'] == 'nn' || $data['langid']['language'] == 'nb' )
				return 'no';

			return $data['langid']['language'];
		}
		
		return null;
    }

    public function post_call($path, $params, $retry = 0 )
    {
		$url = $this->api_url . $path;
		$this->msg( "NLP API $path - $url ");
		$retry++;
		
		if ( $retry > $this->max_retry_count )
		{
			return null;
		}

		$opts = array('http' =>
		    array(
		        'method'  => 'POST',
		        'header'  => 'Content-type: application/x-www-form-urlencoded',
		        'content' => http_build_query( $params ),
		    )
		);
		
		$context  = stream_context_create($opts);
		$result = @file_get_contents($url, false, $context);

		// print_r($http_response_header);
		if ( empty($result) || ( isset($http_response_header) && $http_response_header[0] != 'HTTP/1.0 200 OK' ) ) // empty if server is down
		{
			$this->msg( "Host Failed: {$url}" );
			$this->chooseHost();
			return $this->post_call($path, $params, $retry );
		}

		if ( empty($result) ) return null;

		return json_decode($result, 1);
	}
	
	public function get_call($path, $params, $retry = 0)
	{
		$url = $this->api_url . $path;
		
		$retry++;

		if ( !empty($params) )
			$url  .= '?' . http_build_query( $params );

		$this->msg( "NLP API [GET] $path - $url ");
		$result = @file_get_contents( $url, false );

		if ( empty($result) || ( isset($http_response_header) && $http_response_header[0] != 'HTTP/1.0 200 OK' ) ) // empty if server is down
		{
			$this->msg( "Host Failed: {$url}" );
			$this->chooseHost();
			return $this->get_call($path, $params, $retry );
		}

		if ( empty($result) ) return null;

		return  json_decode($result, 1);

	}
}
