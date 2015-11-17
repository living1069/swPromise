<?php
namespace Core\Async;
/**
 * HttpClientFuture.class.php
 * @author fang
 * @date 2015-11-5
 */
class HttpClientFuture implements FutureIntf {
	protected $url = null;
	protected $post = null;
	protected $timer = null;
	protected $proxy = false;
	protected $timeout = 200;
	
	public function __construct($url, $post = array(), $proxy = array(), $timeout = 200) {
		$this->url = $url;
		$this->post = $post;
		if($proxy){
			$this->proxy = $proxy;
		}
		$this->timeout = $timeout;
	}
	public function run(Promise &$promise) {
		$cli = new \swoole_client ( SWOOLE_TCP, SWOOLE_SOCK_ASYNC );
		$urlInfo = parse_url ( $this->url );
		$timeout = $this->timeout;
		if(!isset($urlInfo ['port']))$urlInfo ['port'] = 80;
		
		$httpParser = new \HttpParser();

		$cli->on ( "connect", function ($cli)use($urlInfo, &$promise, &$timeout){
			Timer::add($cli->sock, $timeout, function()use(&$promise){
				$promise->accept(['http_data'=>null, 'http_error'=>'Read timeout']);
			});

			$host = $urlInfo['host'];
			if($urlInfo['port'])$host .= ':'.$urlInfo['port'];
			$req = array();
			$req[] = "GET {$this->url} HTTP/1.1\r\n";
			$req[] = "User-Agent: PHP swAsync\r\n";
			$req[] = "Host:{$host}\r\n";
			$req[] = "Connection:close\r\n";
			$req[] = "\r\n";
			$req = implode('', $req);
			$cli->send ( $req );
		} );
		
		
		$cli->on ( "receive", function ($cli, $data = "") use(&$httpParser, &$promise) {
			$ret = $httpParser->execute($data);
			if($ret !== false){
				Timer::del($cli->sock);
				if($cli->isConnected())$cli->close();
				$promise->accept(['http_data'=>$ret]);
			}
		} );
		$cli->on ( "error", function ($cli) use(&$promise) {
			Timer::del($cli->sock);
			$promise->accept(['http_data'=>null, 'http_error'=>'Connect error']);
		} );
		$cli->on ( "close", function ($cli) {
		} );
		
		
		$ret = $cli->connect ( $urlInfo ['host'], $urlInfo ['port'], 0.05 );
	}
}