<?php

require_once SYSTEM.'controller.php';
require_once SYSTEM.'actions.php';
require_once SYSTEM.'view_response.php';
require_once SYSTEM.'redirect_response.php';

require_once MODEL.'video.php';

class SearchController extends Controller {

	public static $acceptableSearchFields = ["channel", "video", "tags_select_type", "tags"];
	
	public function __construct() {
		$this->denyAction(Action::GET);
		$this->denyAction(Action::CREATE);
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
					case "video" : $data['videos'] = Video::getSearchVideos($value);
					break;
					case "channel" : $data['channels'] = UserChannel::getSearchChannels($value);
					break;
					case "tags" : $data['videos'] = Video::getSearchVideosByTags(explode(" ", $value), $tags_select_type == "and");
					echo Video::connection()->last_query;
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
			$data['videos'] = Video::getSearchVideos($q);
			$data['channels'] = UserChannel::getSearchChannels($q);
			
			$_SESSION["last_search"] = $q;
		}
		
		
		if(empty($data['videos']) && empty($data['channels'])){
			
			$data['error']= array(
					"message" => "La recherche n'a retourné aucun resultat.",
					"level" => "error"
			);
			
			return new ViewResponse('search/error', $data);
		}
		return new ViewResponse('search/search', $data);
	}
	
	public function update($id="nope", $request) {
		if($id!="advanced"){
			return Utils::getNotFoundResponse();
		}
		$req = $request->getParameters();
		
		$generatedUrl= "search/&advanced=1";
		foreach ($req as $k => $value){
			if(in_array($k, self::$acceptableSearchFields) && !empty($value)){
				$generatedUrl.= "&$k=".urlencode(urlencode($value)); //yep twice
			}
		}
		
		return new RedirectResponse(WEBROOT.$generatedUrl);
	}
	
	public function advanced($request){
		return new ViewResponse("search/advanced");
	}
	
	public function get($id, $request) {}
	public function create($request) {}
	public function destroy($id, $request) {}
}