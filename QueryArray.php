<?php
/**
 * @author Jaykon W. O.
 * @version 1.0.0
 * 
 * A classe QueryArray destina-se a realizar buscas em array multidimencionais em um estilo proximo ao do MongoDB.
 * Muito util para refatorar o retorno de querys utilizadas para criação de relatórios
 * completa a utilização das querys de bancos de dados relacionais, executando tarefas em real-time.
 */
class QueryArray{
	/**
	 * Constante para a utilização da projeção
	 */
	const USE_PROJECTION = 0x001;
	/**
	 * Constante para a não utilização da projeção
	 */
	const NO_PROJECTION = 0x002;
	const ASC = 0x003;
	const DESC = 0x004;
	 
	/**
	 * Parametros privados
	 */
	private $Array;
	private $Cursor_temp;
	private $Projection;
	
	/**
	 * Aponta para o resultado de QueryArray::Find
	 */
	public $Cursor;
	
	
	/**
	 * Construtor da classe
	 * @param Array $ObjArray Objeto a ser pesquisado
	 * @param Array $Cursor Este parametro é de utilização interna da classe e não precisa ser passado
	 * @param String $projection Este parametro é de utilização interna da classe e não precisa ser passado
	 */
	public function __construct(Array &$ObjArray, Array &$Cursor = array(), $projection = null){
		$this->Array =& $ObjArray;
		$this->Cursor =& $Cursor;
		$this->Projection = $projection;
	}
	
	/**
	 * Metodo usado quando a classe é escapada para uma string
	 * @return String Json do objeto Cursor
	 */
	public function __toString(){
		return json_encode($this->Cursor);
	}
	
	/**
	 * Retorna o objeto Cursor
	 * @param QueryArray::NO_PROJECTION || QueryArray::USE_PROJECTION $projection Define se o Cursor será retornado apartir
	 * da projeção ou não
	 * @return QueryArray::Cursor Um Array com os resultados obtidos por QueryArray::Find
	 */
	public function getCursor($projection = self::NO_PROJECTION){
		if($projection == self::USE_PROJECTION){
			$arrFim = array();
			foreach($this->Cursor as &$arr){
				if($this->Projection != null){
					$profundidade = explode(".", $this->Projection);
					
					foreach($profundidade as $p){
						$arr =& $arr[$p];
					}
				}
				$arrFim[count($arrFim)] =& $arr;
			}
			return $arrFim;
		}elseif($projection == self::NO_PROJECTION){
			return $this->Cursor;
		}else{
			$arrFim = array();
			foreach($this->Cursor as &$arr){
				if($projection != null){
					$profundidade = explode(".", $projection);
					
					foreach($profundidade as $p){
						$arr =& $arr[$p];
					}
				}
				$arrFim[count($arrFim)] =& $arr;
			}
			return $arrFim;
		}
	}
	
	/**
	 * Busca no array elementos que combinem com a busca
	 * @param Array $query Query utilizada para buscar nós que combinem com a busca
	 * @param String $projection Projeção da busca dentro do array
	 * @return QueryArray Retorna um novo Objeto QueryArray com o Cursor apontando para o resultado da busca
	 */
	public function Find(Array $query = array(), $projection = null){
		
		$this->Cursor_temp = array();
		if(count($this->Cursor) > 0){
			$Interator =& $this->Cursor;
		}else{
			$Interator =& $this->Array;
		}
		
		foreach($Interator as &$val){
			$val = $val;
			$searchs = count($query);
			$finds = 0;
			
			foreach($query as $Skey => $Sval){
				
				$profundidade = explode(".", $Skey);
				$valorVerif=$val;
				
				foreach($profundidade as $p){
					if (is_array($valorVerif)) {
						$valorVerif = $valorVerif[$p];
					}elseif (is_object($valorVerif)) {
						$valorVerif = $valorVerif->{$p};
					}else{
						throw new Exception("Tipo incorretor de argumento");
						
					}
					
				}
				//echo $valorVerif." - ";
				$this->Verification($valorVerif, $Sval, $finds);
				
			}
				
			if($searchs == $finds){
				$this->Cursor_temp[count($this->Cursor_temp)] =& $val;
			}
			
		}
		
		return new QueryArray($this->Array, $this->Cursor_temp, $projection);
	}
	
	/**
	 * Atualiza os dados do array baseado no Cursor retornado por QueryArray::Find
	 * @param Array $sets Array associativo com o par de atualização Chave/Valor
	 * @return QueryArray Atualiza o Cursor e retorna o propri Objeto
	 */
	public function Update(Array $sets){
		
		foreach($sets as $setKey => $setVal){
			foreach($this->Cursor as &$cur){
				
				if($this->Projection != null){
					$profundidade2 = explode(".", $this->Projection);
					
					foreach($profundidade2 as $p){
						$cur =& $cur[$p];
					}
				}
				
				
				$profundidade = explode(".", $setKey);	
				foreach($profundidade as $p){
					$cur =& $cur[$p];
				}
				
				if(is_array($setVal)){
					$func = $setVal[0];
					$par = array_slice($setVal, 1);
					
					foreach($par as &$params){
						$params = str_replace("{.}", $cur, $params);
					}
					$cur = call_user_func_array($func, $par);
				}else{
					$comand = "";
					@eval("\$comand = ". str_replace("{.}", $cur, $setVal).";");
					$cur = $comand;
				}
			}
		}
		
		return $this;
	}
	
	
	public function UpdateKey(Array $sets){
		
		foreach($sets as $setKey => $setVal){
			foreach($this->Cursor as &$cur){
				
				if($this->Projection != null){
					$profundidade2 = explode(".", $this->Projection);
					
					foreach($profundidade2 as $p){
						$cur =& $cur[$p];
					}
				}
				
				
				$profundidade = explode(".", $setKey);
				$profForKeys = array_slice($profundidade, 0, -1);	
				foreach($profForKeys as $p){
					$cur =& $cur[$p];
				}
				
				if(is_array($cur)){
					$cur[$setVal] = $cur[$profundidade[count($profundidade) - 1]];
					unset($cur[$profundidade[count($profundidade) - 1]]);
				}
			}
		}
		
		return $this;
	}
	
	
	public function DropKey(Array $sets){
		
		foreach($sets as $setKey){
			foreach($this->Cursor as &$cur){
				
				if($this->Projection != null){
					$profundidade2 = explode(".", $this->Projection);
					
					foreach($profundidade2 as $p){
						$cur =& $cur[$p];
					}
				}
				
				
				$profundidade = explode(".", $setKey);
				$profForKeys = array_slice($profundidade, 0, -1);	
				foreach($profForKeys as $p){
					$cur =& $cur[$p];
				}
				
				if(is_array($cur)){
					unset($cur[$profundidade[count($profundidade) - 1]]);
				}
			}
		}
		
		return $this;
	}
	
	
	public function Sort($key, $direction = self::ASC){
		$arr = array();
		foreach($this->Cursor as $cur){
				
			$profundidade = explode(".", $key);	
			$curProject = $cur;
			foreach($profundidade as $p){
				$curProject = $curProject[$p];
			}
			array_push($arr, $curProject);
		}
		
		if($direction == self::ASC){
			sort($arr);
		}elseif($direction == self::DESC){
			rsort($arr);
		}
		
		$this->Cursor_temp = array();
		foreach($arr as $sortArr){
			$cur =& $this->Cursor;
			foreach($cur as $keys => $val){
				$profundidade = explode(".", $key);	
				$curProject = $cur[$keys];
				foreach($profundidade as $p){
					$curProject = $curProject[$p];
				}
				//echo print_r($curProject)." = ".$sortArr;
				if($curProject == $sortArr){
					$this->Cursor_temp[count($this->Cursor_temp)] = $cur[$keys];
					//echo(print_r($cur[$keys])."<br>");
					unset($cur[$keys]);
				}
			}
			/*
			foreach($this->Cursor as &$cur){
					
				$profundidade = explode(".", $key);	
				$curProject = $cur;
				foreach($profundidade as $p){
					$curProject = $curProject[$p];
				}
				
				if($curProject == $sortArr){
					$this->Cursor_temp[count($this->Cursor_temp)] =& $cur;
					exit(print_r(current($cur)));
					unset(current($this->Cursor));
				}
			}*/
		}
		
		return new QueryArray($this->Array, $this->Cursor_temp, $this->Projection);
		
	}
	
	
	public static function Copy(Array $array) {
        $result = array();
        foreach( $array as $key => $val ) {
            if( is_array( $val ) ) {
                $result[$key] = self::Copy( $val );
            } elseif ( is_object( $val ) ) {
                $result[$key] = clone $val;
            } else {
                $result[$key] = $val;
            }
        }
        return $result;
	}
	
	/**
	 * @ignore
	 * Realiza as verificações das funções de busca
	 */
	private function Verification($valorVerif, $Sval, &$finds){
		
		if(is_array($Sval)){
			foreach($Sval as $funcKey => $funcVal){
				//echo $funcKey." - ";
				switch(strtolower($funcKey)){
					case '$in' :
						$this->QueryIn($valorVerif, $funcVal, $finds, false);
						break;
					case '$>' :
						$this->QueryGt($valorVerif, $funcVal, $finds, false);
						break;
					case '$>=' :
						$this->QueryGtEqual($valorVerif, $funcVal, $finds, false);
						break;
					case '$<' :
						$this->QueryLt($valorVerif, $funcVal, $finds, false);
						break;
					case '$<=' :
						$this->QueryLtEqual($valorVerif, $funcVal, $finds, false);
						break;
					case '$between' :
						$this->QueryBetween($valorVerif, $funcVal, $finds, false);
						break;
					case '$exp' :
						$this->QueryExp($valorVerif, $funcVal, $finds, false);
						break;
					case '$or' :
						$this->QueryOr($valorVerif, $funcVal, $finds, false);
						break;
					case '$!in' :
						$this->QueryIn($valorVerif, $funcVal, $finds, true);
						break;
					case '$!>' :
						$this->QueryGt($valorVerif, $funcVal, $finds, true);
						break;
					case '$!>=' :
						$this->QueryGtEqual($valorVerif, $funcVal, $finds, true);
						break;
					case '$!<' :
						$this->QueryLt($valorVerif, $funcVal, $finds, true);
						break;
					case '$!<=' :
						$this->QueryLtEqual($valorVerif, $funcVal, $finds, true);
						break;
					case '$!between' :
						$this->QueryBetween($valorVerif, $funcVal, $finds, true);
						break;
					case '$!exp' :
						$this->QueryExp($valorVerif, $funcVal, $finds, true);
						break;
					case '$!or' :
						$this->QueryOr($valorVerif, $funcVal, $finds, true);
						break;
					default :
						//throw new Exception($funcKey." não é uma função de ".get_class($this));
						trigger_error("<b>".$funcKey."</b> não é uma função de ".get_class($this), E_USER_WARNING);
				}
			}
		}else{
			$Sval = str_replace(" ", "_", $Sval);
			if($Sval == null){
				if($valorVerif == $Sval){
					$finds++;
				}
			}else{
				if(preg_match("/\b({$Sval})\b/i",str_replace(" ", "_", $valorVerif))){
					$finds++;
				}
			}
		}
	}
	
	/**
	 * @ignore
	 * Alias da função SQL: IN
	 */
	private function QueryIn($valorVerif, $funcVal, &$finds, $inverse){
		if($inverse) {
			$val = implode("|", $funcVal);
			$val = str_replace(" ", "_", $val);
			if(!preg_match("/\b({$val})\b/i",str_replace(" ", "_", $valorVerif))) $finds++;
		}
		else{
			$val = implode("|", $funcVal);
			$val = str_replace(" ", "_", $val);
			if(preg_match("/\b({$val})\b/i",str_replace(" ", "_", $valorVerif))) $finds++;
		}
	}
	
	/**
	 * @ignore
	 * Alias da função SQL: >
	 */
	private function QueryGt($valorVerif, $funcVal, &$finds, $inverse){
		if($inverse) {
			$this->QueryLtEqual($valorVerif, $funcVal, $finds, false);
		}
		else{
			if((int)$valorVerif > (int)$funcVal){
				$finds++;
			}	
		}
	}
	
	/**
	 * @ignore
	 * Alias da função SQL: >=
	 */
	private function QueryGtEqual($valorVerif, $funcVal, &$finds, $inverse){
		if($inverse) {
			$this->QueryLt($valorVerif, $funcVal, $finds, false);
		}
		else{
			if((int)$valorVerif >= (int)$funcVal){
				$finds++;
			}	
		}
	}
	
	/**
	 * @ignore
	 * Alias da função SQL: <
	 */
	private function QueryLt($valorVerif, $funcVal, &$finds, $inverse){
		if($inverse) {
			$this->QueryGtEqual($valorVerif, $funcVal, $finds, false);
		}
		else{
			if((int)$valorVerif < (int)$funcVal){
				$finds++;
			}	
		}
	}
	
	/**
	 * @ignore
	 * Alias da função SQL: <=
	 */
	private function QueryLtEqual($valorVerif, $funcVal, &$finds, $inverse){
		if($inverse) {
			$this->QueryLt($valorVerif, $funcVal, $finds, false);
		}
		else{
			if((int)$valorVerif <= (int)$funcVal){
				$finds++;
			}	
		}
	}
	
	/**
	 * @ignore
	 * Alias da função SQL: BETWEEN
	 */
	private function QueryBetween($valorVerif, $funcVal, &$finds, $inverse){
		if($inverse) {
			if((int)$valorVerif < (int)$funcVal[0] || (int)$valorVerif > (int)$funcVal[1]){
				$finds++;
			}	
		}
		else{
			if((int)$valorVerif >= (int)$funcVal[0] && (int)$valorVerif <= (int)$funcVal[1]){
				$finds++;
			}	
		}
	}
	
	/**
	 * @ignore
	 * Busca com expressões regulares
	 */
	private function QueryExp($valorVerif, $funcVal, &$finds, $inverse){
		if($inverse) {
			if(!preg_match($funcVal, $valorVerif)){
				$finds++;
			}		
		}
		else{
			if(preg_match($funcVal, $valorVerif)){
				$finds++;
			}	
		}
	}
	
	/**
	 * @ignore
	 * Alias da função SQL: OR
	 */
	private function QueryOr($valorVerif, $funcVal, &$finds, $inverse){
		
	}
}


?>