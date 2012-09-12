<?php
class rewriteConf {
/*
	function __construct($htContent){
		$this->htContent = $htContent;
	}
*/
	function parseLine($line){
		list($cmd,$regex,$rew,$flags) = explode(" ",$line);
		if(!empty($flags)){
			$flagsArray = explode(",",trim(substr(substr(trim($flags),strpos($flags,'[')+1),0,strpos($flags,']')-1)));
			$flagsArray = array_map("trim",$flagsArray);
		}
		return array(trim($regex),trim($rew),$flagsArray);
	}

	function readRules(){
		$lines = explode("\n",$this->htContent);
		$i = 0;
		foreach($lines as $line){
			if($line[0] != '#' && !empty($line[0])){
				if(strpos($line,"RewriteCond") !== false){
					if(!isset($k))$k = 0;
					if(!isset($p))$p = 0;
					$parsedLine = $this->parseLine($line);
					$conds[$p] = array(
						'match' => $parsedLine[0],
						'rule' => $parsedLine[1],
						'flags' => $parsedLine[2]
					);
					$p++;
				}
				if(strpos($line,"RewriteRule") !== false){
					if(!isset($k))$k = 0;
					$parsedLine = $this->parseLine($line);
					$condBit = 'AND';
					if(is_array($conds[0]["flags"]) && in_array('OR',$conds[0]["flags"]))$condBit = 'OR';
					$rules[$k] = array(
						'rule' => array(
							'regex' => $parsedLine[0],
							'rew' => $parsedLine[1],
							'flags' => $parsedLine[2]
						),
						'condBit' => $condBit,
						'conditions' => $conds
					);						
					unset($p,$conds,$parsedLine,$condBit);
					$k++;
				}							
			}
		}
		return $rules;
	}

	
	function parseFlags($flagArray,$type,$r,$c){
		//type: 0 -> rule 1-> condition
		//this function returns an array;
		/*
			array(
				return => 0|return code
				break => 0|1
				appendEnd => last|permanent|redirect|break
				env => var=value
				matchOperator => ~*|~
				unknown => 0|1
				set => key:var
			)
		*/
		$returnArray = array('return' => '0','break' => '0','appendEnd' => '', 'env' => '', 'matchOperator' => '~');
		foreach($flagArray as $flag){
			switch($flag[0]){
				case 'N':
				case 'n':
					$returnArray["matchOperator"] = '~*';
					if(!isset($returnArray["unknown"]))$returnArray["unknown"] = '0';
				break;
				case 'F':
				case 'f':
					$returnArray["return"] = '403';
					$returnArray["break"] = '1';
					if(!isset($returnArray["unknown"]))$returnArray["unknown"] = '0';
				break;
				case 'G':
				case 'g':
					$returnArray["return"] = '410';
					$returnArray["break"] = '1';
					if(!isset($returnArray["unknown"]))$returnArray["unknown"] = '0';
				break;
				case 'R':
				case 'r':
					list($flaga,$rcode) = explode("=",$flag);
					if($rcode == '301' || $rcode == 'permanent'){
						$returnArray["appendEnd"] = 'permanent';
					} else {
						$returnArray["appendEnd"] = 'redirect';
					}
					$returnArray["break"] = '1';
					if(!isset($returnArray["unknown"]))$returnArray["unknown"] = '0';
				break;
				case 'L':
				case 'l':
					if(empty($returnArray["appendEnd"])){
						$returnArray["appendEnd"] = 'last';
					}
					if(!isset($returnArray["unknown"]))$returnArray["unknown"] = '0';
				break;
				case 'E':
				case 'e':
					list($cmd,$envvar) = explode("=",$flag);
					//list($ekey,$evar) = explode(":",$envvar);
					$returnArray["env"][] = $envvar;
				break;
				case 'O':
				case 'o':
					$returnArray["set"][] = '$rule_'.$r.' 1';
				break;
				case 'A':
					$returnArray["set"][] = '$rule_'.$r.' '.($c+1).'$rule_'.$r;
				break;
			}
		}
		if(count($flagArray) > 1){
			if(!isset($returnArray["unknown"]))$returnArray["unknown"] = '1';
		} else {
			$returnArray["unknown"] = '0';
		}
		return $returnArray;
		
	}

	function parseCondMatch($condition,$r,$c,$condBit){
		$matchOperator = '~';

		if($condition["rule"][0] == '!'){
			$nomatch = '!';
			$condition["rule"] = substr($condition["rule"],1);
		}

		$condition["flags"][] = $condBit;
		$condition["flags"] = array_unique($condition["flags"]);
		

		$fromFlags = $this->parseFlags($condition["flags"],1,$r,$c);


		if($fromFlags["matchOperator"]){
			$matchOperator = $fromFlags["matchOperator"];
		}

		switch($condition["rule"]){
			case "-f":
			case "-F":
				$left = $nomatch.'-f';
				$right = $condition["match"];
				$operand = '';
			break;
			case "-d":
				$left = $nomatch.'-d';
				$right = $condition["match"];
				$operand = '';
			break;	
			case "-s":
				$left = $nomatch.'-e';
				$right = $condition["match"];
				$operand = '';
			break;
			default:
				$left = $condition["match"];
				$right = $condition["rule"];
				$operand = $nomatch.$matchOperator;

			break;
		}

		$returnArray = array(
			'left' => $left,
			'right' => $right,
			'operand' => $operand,
			'flags' => $fromFlags
		);
		return $returnArray;		
	}

	function parseRewirteCond($rule,$r){
		$c = 0;
		if(is_array($rule["conditions"])){
			foreach($rule["conditions"] as $condition){
				
					$condResult[$c] = $this->parseCondMatch($condition,$r,$c,$rule["condBit"]);				
				$c++;
			}
		}
		return $condResult;
	}

	function mustSkipForCond($conditions){
		if(count($conditions)==0){
			return 0;
		} elseif(count($conditions) == 1) {
			if($conditions[0]["flags"]["unknown"] == 1){
				return "skipped because all flags in condition are unknown";
			}
			return 0;
		} else {
			$unknown = 0;
			foreach($conditions as $cond){
				if($cond["flags"]["unknown"] == 1){
					$unknown++;
				}
			}
			if(count($conditions) == $unknown){
				return "skipped because all flags for all conditions are unknown";
			}
			return 0;
		}
	}

	function setBackRef(&$rule,&$condsParsed){
		if(preg_match_all('/\%([0-9])/',$rule["rule"]["regex"],$matchesRule)!=0){
			$rule["rule"]["regex"] = preg_replace('/\%([0-9])/','$bref_$1',$rule["rule"]["regex"]);			
		}
		
		if(preg_match_all('/\%([0-9])/',$rule["rule"]["rew"],$matchesRew)!=0){
			$rule["rule"]["rew"] = preg_replace('/\%([0-9])/','$bref_$1',$rule["rule"]["rew"]);
		}

		$totalMatches = array_merge($matchesRule[1],$matchesRew[1]);
		

		if(is_array($rule["rule"]["flags"])){
			$i=0;
			foreach($rule["rule"]["flags"] as $flags){
				if(preg_match_all('/\%([0-9])/',$rule["rule"]["flags"][$i],$matchesFlag)!=0){
					$rule["rule"]["flags"][$i] = preg_replace('/\%([0-9])/','$bref_$1',$rule["rule"]["flags"][$i]);
					$totalMatches = array_merge($totalMatches,$matchesFlag[1]);
				}
				$i++;
			}
		}
		
		$totalMatches = array_unique($totalMatches);
		if(is_array($totalMatches)){
			foreach($totalMatches as $match){
				$i=0;
				if($rule["condBit"] == 'OR'){
					foreach($condsParsed as $cond){
						array_push($condsParsed[$i]["flags"]["set"], '$bref_'.$match.' $'.$match);
						$i++;
					}
				} else {					
					array_push($condsParsed[count($condsParsed)-1]["flags"]["set"], '$bref_'.$match.' $'.$match);
				}
			}
		}
		

	}

	function parseRule(&$rule,$c){	

		if($rule["rule"]["regex"][0] == '^'){
			if($rule["rule"]["regex"][1]!='/'){
				$rule["rule"]["regex"] = '^/'.substr($rule["rule"]["regex"],1);
			}
		} else {
			if($rule["rule"]["regex"][0]!='/'){
				$rule["rule"]["regex"] = '/'.$rule["rule"]["regex"];
			}						
		}
		
		if($rule["rule"]["rew"][0] == '^' && substr($rule["rule"]["rew"],0,4) != 'http'){
			if($rule["rule"]["rew"][1]!='/'){
				$rule["rule"]["rew"] = '^/'.substr($rule["rule"]["rew"],1);
			}
		} else {
			if($rule["rule"]["rew"][0]!='/' && substr($rule["rule"]["rew"],0,4) != 'http'){
				$rule["rule"]["rew"] = '/'.$rule["rule"]["rew"];
			}						
		}
		
		if($rule["rule"]["rew"] == '/-'){
			unset($rule["rule"]["rew"],$rule["rule"]["regex"]);
		}

		
		if($c != 0){
			
			if($rule["condBit"] == 'OR'){
				$rule["rule"]["trueExp"] = '1';
			} else {
				$i=0;
				while($i < $c){
					$backme = ($i+1).$backme;
					$i++;
				}
				$rule["rule"]["trueExp"] = $backme;
			}
		}
	}
	
	function replaceVariables(&$val,&$key){
		
		if(preg_match_all('/\%\{HTTP\:(.*)\}/',$val,$matches)){
			foreach($matches[1] as $match){
				$val = str_replace('%{HTTP:'.$match.'}','$http_'.str_replace('-','_',strtolower($match)),$val);
			}
		}
		
		
		$pat = array(
			'%{HTTP_USER_AGENT}',
			'%{HTTP_REFERER}',
			'%{HTTP_COOKIE}',
			'%{HTTP_FORWARDED}',
			'%{HTTP_HOST}',
			'%{HTTP_PROXY_CONNECTION}',
			'%{HTTP_ACCEPT}',
			'%{REMOTE_ADDR}',
			'%{REMOTE_PORT}',
			'%{REMOTE_USER}',
			'%{REQUEST_METHOD}',
			'%{SCRIPT_FILENAME}',
			'%{PATH_INFO}',
			'%{QUERY_STRING}',
			'%{DOCUMENT_ROOT}',
			'%{SERVER_NAME}',
			'%{SERVER_ADDR}',
			'%{SERVER_PORT}',
			'%{SERVER_PROTOCOL}',
			'%{REQUEST_URI}',
			'%{REQUEST_FILENAME}'			
		);
		
		$rep = array(
			'$http_user_agent',
			'$http_referer',
			'$http_cookie',
			'$http_forwarded',
			'$http_host',
			'$http_proxy_connection',
			'$http_accept',
			'$remote_addr',
			'$remote_port',
			'$remote_user',
			'$request_method',
			'$uri',
			'$uri',
			'$args',
			'$document_root',
			'$server_name',
			'$server_addr',
			'$server_port',
			'$server_protocol',
			'$uri',
			'$request_filename'																			
		);
		$oldVal = $val;
		$val = str_replace($pat,$rep,$val);	
	
		if($oldVal == $val && preg_match('/\%\{(.*)\}/i',$val)!=0){			
			$val = "IGNORE";
		}		
	}

	
	function walkRecursive(&$input, $funcname, $userdata = ""){
       
        if (!is_array($input)){
            return false;
        }
       
        foreach ($input AS $key => $value){
            if(is_array($input[$key])){
                $this->walkRecursive($input[$key], $funcname, $userdata);
            } else {
                $saved_value = $value;
                if(!empty($userdata)){
                    $this->$funcname($value, $key, $userdata);
                } else {
                    $this->$funcname($value, $key);
                }
               
                if($value != $saved_value){
                	if($value == 'IGNORE'){
						unset($input[$key]);	
					} else {
						$input[$key] = $value;						
					}                    
                }
            }
        }
        return true;
    }
	
	function writeConfig(){		
		$r = 0;
		foreach($this->conf as $conf){
			if(is_array($conf)){					
				//array_walk_recursive($conf,'rewriteConf::replaceVariables');
				$this->walkRecursive($conf,'replaceVariables');
				//print_r($conf);
				//exit;
				$c = 0;
				if(is_array($conf["conds"])){			
					foreach($conf["conds"] as $cond){				
						if($cond["flags"]["unknown"]!=1 && isset($cond["left"]) && isset($cond["right"])){
							if($cond["operand"] == ''){
								$ret.= 'if ('.$cond["left"].' '.$cond["right"].'){
';
							} else {
								$ret.= 'if ('.$cond["left"].' '.$cond["operand"].' "'.$cond["right"].'"){
';
							}
							if(is_array($cond["flags"]["set"])){
								foreach($cond["flags"]["set"] as $set){
									$ret.= '	set '.$set.';
';
								}
							}
							
							if(is_array($cond["flags"]["env"])){						
								foreach($cond["flags"]["env"] as $env){
									$ret.= '	setenv '.$env.';
';
								}
							}
							if($conf["condBit"] == 'OR'){							
								if($conf["rule"]["flags"]["return"] > 0){
									$ret.= '	return '.$conf["rule"]["flags"]["return"].';
';
									$isReturned = 1;
								}
								if($conf["rule"]["flags"]["break"] == 1){
									$ret.= '	break;
';
								}
							}
							$ret.='}
';
				
						} else {
							$conf["rule"]["trueExp"] = str_replace($c,'',$conf["rule"]["trueExp"]);
							$ret.= '#ignored: condition '.$c.'
';
						}
						$c++;					
					}				
				}				
				if(!isset($isReturned)){
					if($conf["rule"]["flags"]["unknown"] != 1){

						if(!empty($conf["rule"]["trueExp"]))
						{
							$ret.= 'if ($rule_'.$r.' = "'.$conf["rule"]["trueExp"].'"){
';
						}
						
						if($conf["rule"]["flags"]["return"] < 1){
							
							if(is_array($conf["rule"]["flags"]["set"])){
								foreach($conf["rule"]["flags"]["set"] as $set){
									$ret.= '	set '.$set.';
';
								}
							}
							
							if(is_array($conf["rule"]["flags"]["env"])){
								foreach($conf["rule"]["flags"]["env"] as $env){
									$ret.= '	setenv '.$env.';
';
								}
							}
							if(isset($conf["rule"]["regex"]) && isset($conf["rule"]["rew"])){
								if(!empty($conf["rule"]["flags"]["appendEnd"])){
									$conf["rule"]["flags"]["appendEnd"] = ' '.$conf["rule"]["flags"]["appendEnd"];
								}
								$ret.= '	rewrite '.$conf["rule"]["regex"].' '.$conf["rule"]["rew"].''.$conf["rule"]["flags"]["appendEnd"].';
';
							} else {
								$ret.= '#ignored: "-" thing used or unknown variable in regex/rew 
';
							}
						} else {
							$ret.= '	return '.$conf["rule"]["flags"]["return"].';
';
							if($conf["rule"]["flags"]["break"] == 1){
								$ret.= '	break;
';
							}
						}
						if(!empty($conf["rule"]["trueExp"]))
						{
							$ret.='}
';
						}
					} else {
						$ret.= '#ignored: unknown variable in rule flag
';
					}
				}
				unset($isReturned,$cond,$env,$set);
			} else {
				$ret.= $conf.'
';
			}
			$r++;
		}
		$this->confOk = $ret;
	}

	function parseContent(){
		$this->rules = $this->readRules();
		$r = 0;
		foreach($this->rules as $rule){
			$condsParsed = $this->parseRewirteCond($rule,$r);
			$beforeMscr = $mscr;
			if(($mscr = $this->mustSkipForCond($condsParsed))!=0){
				$conf[$r]["conds"] = $mscr;
				$conf[$r]["rule"] = $mscr;
			} else {
				if($beforeMscr != 0){
					//set last|break to before rule if any
				}
				$this->setBackRef($rule,$condsParsed);
				if(is_array($rule["rule"]["flags"])){
					$rule["rule"]["flags"] = $this->parseFlags($rule["rule"]["flags"],0,$r,0);
				} else {
					unset($rule["rule"]["flags"]);
				}
				$this->parseRule($rule,count($condsParsed));
				$conf[$r]["conds"] = $condsParsed;
				$conf[$r]["rule"] = $rule["rule"];				
			}
			$conf[$r]["condBit"] = $rule["condBit"];
			$r++;
		}
		$this->conf = $conf;
	}

}
?>