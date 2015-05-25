<?php

require_once SYSTEM.'controller.php';
require_once SYSTEM.'actions.php';
require_once SYSTEM.'view_response.php';
require_once SYSTEM.'json_response.php';
require_once SYSTEM.'redirect_response.php';

require_once MODEL . 'word_search.php';
require_once MODEL.'video.php';

class SearchController extends Controller {

	public static $acceptableSearchFields = ["channel", "video", "tags_select_type", "tags", "order", "order_way"];
	
	public function __construct() {
		$this->denyAction(Action::GET);
		$this->denyAction(Action::DESTROY);
	}
	
	public function index($request) {

		/* SECURING */ 
		if(!(isset($_GET['q']) || isset($_GET['advanced']))){
			return Utils::getNotFoundResponse();
		}
		$filteredFileds = [];
		foreach ($_GET as $k => $value){
			if(in_array($k, array_merge(self::$acceptableSearchFields,  ["q", "advanced"])) && !empty($value)){
				$filteredFileds[$k] = $value;
			}
		}
		if(empty($filteredFileds)){
			return Utils::getNotFoundResponse();
		}
		/* END SECURING */
		
		$data = [];
		$data['filteredFields'] = $filteredFileds;
		$order = "none";
		if(isset($_GET[ "order"], $_GET["order_way"])){
			if(in_array($_GET['order'], ['views', 'likes', 'timestamp'])
					&& in_array($_GET['order_way'], ['ASC', 'DESC'])){
				$order = $_GET['order'] . " " . $_GET['order_way'];
				$filteredFileds['oreder']=$_GET['order'];
				$filteredFileds['oreder_way']=$_GET['order_way'];
			}
		}
		
		/* Advanced */
		if(@$filteredFileds['advanced']==1){
			$data['currentPageTitle'] = 'Recherche avancée';
			
			
			$tags_select_type = "or";
			if(isset($filteredFileds['tags_select_type'])){
				if(in_array($filteredFileds['tags_select_type'], ["or", "and"])){
					$tags_select_type = $filteredFileds['tags_select_type'];
				}
			}
			
			
			foreach ($filteredFileds as $k => $value) {
				switch ($k){
					case "video" : $data['videos'] = Video::getSearchVideos($value, $order);
					break;
					case "channel" : $data['channels'] = UserChannel::getSearchChannels($value);
					break;
					case "tags" : 
						if(isset($data['videos'])){
							$data['videos'] = array_merge($data['videos'], Video::getSearchVideosByTags(explode(" ", $value), $order, $tags_select_type == "and"));
						}else{
							$data['videos'] = Video::getSearchVideosByTags(explode(" ", $value), $order, $tags_select_type == "and");
						}

					break;
				}
				$_SESSION["last_search"] = "";
			}
				$data['currentPageTitle'] = 'Recherche avancée';
				$data['search'] = "avancée";
			
		}else{ /* Normal search */
			$q = urldecode($_GET['q']);
			
			$data['currentPageTitle'] = $q.' - Recherche';
			$data['search'] = $q;
			$data['videos'] = Video::getSearchVideos($q, $order);
			$data['channels'] = UserChannel::getSearchChannels($q);
			
			$_SESSION["last_search"] = $q;
		}
		
		
		if((empty($data['videos']) && empty($data['channels'])) || strlen($_GET['q']) < 3){
			
			$data['error']= array(
					"message" => "La recherche n'a retourné aucun resultat.",
					"level" => "error"
			);
			
			return new ViewResponse('search/error', $data);
		}
		return new ViewResponse('search/search', $data);
	}
	
	public function update($id="nope", $request) {
		if($id!= "advanced" && $id != "order"){
			return Utils::getNotFoundResponse();
		}
		$req = $request->getParameters();
		
		$generatedUrl= "search/". ($id=="advanced" || isset($req['advanced']) ? "&advanced=1" : "");
		foreach ($req as $k => $value){
			if(in_array($k, array_merge(self::$acceptableSearchFields, ["q"])) && !empty($value)){
				$generatedUrl.= "&$k=".urlencode(urlencode($value)); //yep twice
			}
		}
		
		return new RedirectResponse(WEBROOT.$generatedUrl);
	}
	
	public function advanced($request){
		return new ViewResponse("search/advanced");
	}
	
	public function autocomplete($query, $request){
		$data = [];
		
		$words = explode(' ', $query);
		$words = array_reverse($words);
		$word_to_complete = '';
		foreach($words as $k => $word){
			if(strlen($word)>=3){
				$word_to_complete = $word;
				break;
			}
		}
		$data=WordSearch::getMatchingWords($word);
		return new JsonResponse($data);
	}

	public function create($request) {
		$req = $request->getParameters();
		if(isset($req['add_words'])){
			$words = explode(" ", $req['query']);
			$words = array_unique($words);
			$words = array_filter($words, function ($v){
				return strlen($v) >= 3;
			});
			return new JsonResponse(["ok"]);
		}
	}
	
	public function get($id, $request) {}
	public function destroy($id, $request) {}
}