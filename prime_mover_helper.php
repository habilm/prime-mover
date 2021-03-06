<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if(!function_exists("form_builder")){
/**
 * $table => form : array(
 *  )
 * $table => form_filter_hooks : array(<hook_name>: array( <field_name/column_name> ) ) 
 * eg:"before_submit"=>["category_id"], the function will be before_submit_category_id
 */
    function form_builder($edit=[],$options=[]){
        if(empty($edit)){
            $edit = $_GET;
        }
        $edit = (array)$edit;
        echo isset($edit["id"])?"<input type='hidden' name='id' value='{$edit['id']}' >":"";
        $cl =& get_instance();
        
        $table_config = isset($cl->config->item("form_structure")[$cl->router->class])?$cl->config->item("form_structure")[$cl->router->class]:[];

        if(isset($table_config["form"]) || isset($options["is_custom_form_data"])){
            echo '<div class="box-body">';
            $form_data = isset($options["is_custom_form_data"])?$edit:$table_config["form"];
            if(isset($options["is_custom_form_data"]) && isset($options["edit"])){
                $edit = $options["edit"];
            }
            foreach($form_data as $frow){
                ?>
                <div class="row">

                <?php 
                foreach($frow as $key => $col){
                    $attributes=array("type"=>"text","label"=>$col,"pattern"=>"","rows"=>"3","class"=>"form-control form-control-sm","maxlength"=>255);
                    if(is_array($col)){
                        $fm_control = str_replace(" ","",$key);
                        $attributes["placeholder"] = $attributes["label"] = ucfirst(str_replace("_"," ",$key));
                        $attributes = array_merge($attributes,$col);
                        $attributes["value"] = (!empty($edit[$fm_control]) || ( isset($edit[$fm_control]) && is_numeric($edit[$fm_control]) ) )?$edit[$fm_control]:(  isset($col["value"])?$col["value"]:"");
                        if(isset($col["rules"])){
                            $rules = preg_split('/\|(?![^\[]*\])/', $col["rules"]);
                            foreach($rules as $rule){
                                $attributes = array_merge($attributes,to_html_attr($rule));
                            }
                            unset($attributes["rules"]);
                        }
                        if(isset($attributes["name"])){
                            $fm_control= str_replace(" ","",trim($attributes["name"]));
                        }
                        $attributes["placeholder"] = str_replace("_"," ",$attributes["placeholder"]);
                    }else{
                        $attributes["value"] = (!empty($edit[$col]) || ( isset($edit[$col]) && is_numeric($edit[$col]) ))?$edit[$col]:(  isset($col["value"])?$col["value"]:"");
                        $fm_control = str_replace(" ","",trim($col));
                    }

                    //filter_hooks 
                    
                    if(isset($table_config["form_filter_hooks"]["before_create_input"]) && in_array($fm_control,$table_config["form_filter_hooks"]["before_create_input"])){
                        if(function_exists($hook_function_name = "before_create_input_".$fm_control)){
                            $attributes = call_user_func($hook_function_name,$cl,$attributes);
                        }else{
                            echo '<h4 class="text-danger">function not found</h4>';
                        }
                        die("o0");
                    }
                    $html_attr = $parent_facts = "";
                    foreach($attributes as $attr_name => $attribute){
                        if(is_array($attribute) && $attr_name=="parent"){
                            $parent_facts.="data-parent-name='{$attribute[0]}' data-parent-value='".(isset($attribute[1])?$attribute[1]:"")."'";
                            $html_attr.="disabled='disabled'";
                        }
                        if(!is_array($attribute) && !in_array($attr_name,["label","table","table_column"]) && ( is_numeric($attribute) || is_numeric($attribute) || !empty(trim($attribute)) ) ){
                            $html_attr.="$attr_name='$attribute' ";
                        }
                    }

                    if($attributes["type"]=="hidden"){
                        ?>
                            <input type="hidden" <?= $html_attr ?> name="<?=$fm_control ?>" >
                        <?php
                    }elseif(in_array($attributes["type"],["checkbox","radio"])){
                        ?>
                            <div <?= $parent_facts ?> class="col-sm-<?=isset($attributes["colspan"])?$attributes["colspan"]:( floor( 12/count($frow)) ) ?>">
                                <div class="form-group">
                                <label><?=ucfirst($attributes["label"])?></label><br>
                                    <?php 
                                    $checkbox_name = $fm_control."[]";
                                    $checkbox_values = isset($edit[$fm_control])?explode(",",$edit[$fm_control]):[];
                                    $checkbox_values = empty($checkbox_values) && isset($attributes["value"])?[$attributes["value"]]:$checkbox_values;

                                    if(!is_array($attributes["values"])){
                                        $attributes["values"] = [1=>$attributes["values"]];
                                        $checkbox_name = $fm_control;
                                    } 
                                    if($attributes["type"] == "radio"){
                                        $checkbox_name = $fm_control;
                                    } 
                                    foreach($attributes["values"] as $key => $label){ 
                                        ?>
                                        <label style='display:inline-block;width:auto' <?= $html_attr ?> > <input type="<?=$attributes["type"] ?>" <?= in_array($key,$checkbox_values)?"checked":"" ?>  value="<?= $key?>" name="<?= $checkbox_name ?>" > <?=ucfirst($label)?></label>
                                    <?php } 
                                    ?>
                                </div>
                            </div>
                        <?php
                    }
                    else{
                        ?>
                        <div <?= $parent_facts ?> class="col-sm-<?=isset($attributes["colspan"])?$attributes["colspan"]:( floor( 12/count($frow)) ) ?>">
                            <div class="form-group">
                                <label><?=ucfirst($attributes["label"])?></label>
                                <?php if($attributes["type"]=="textarea"){ ?>
                                    <textarea name="<?=$fm_control ?>" <?= $html_attr ?>><?= isset($edit[$fm_control])?$edit[$fm_control]:$attributes["value"] ?></textarea>
                                <?php }elseif($attributes["type"]=="select"){
                                    
                                    
                                    ?>
                                    <select name="<?=$fm_control.(isset($attributes["multiple"]) && $attributes["multiple"]=="multiple"?"[]":"") ?>"  <?= $html_attr ?> >
                                        <?php
                                            if(isset($attributes["table"])){
                                                $value_key = isset($attributes["key"])?$attributes["key"]:"id";

                                                $table_val = isset($attributes["table_val"])?$attributes["table_val"]:"name";
                                               
                                                if(isset($attributes["table_query"]) && is_array($attributes["table_query"])){
                                                    foreach($attributes["table_query"] as $method=>$query){
                                                        $cl->db->{$method}($query);
                                                    }
                                                }

                                                $result = $cl->db->get_where($attributes["table"],["_trash"=>0])->result();
                                                // $value_array = (array)json_decode($edit[$fm_control]);
                                                $value_array = isset($edit[$fm_control])?explode(",",$edit[$fm_control]):[] ;

                                                echo isset($attributes["value"]) ?"<option value=''  >--Select $fm_control--</option>":"";
                                                foreach($result as $row){
                                                    $selected="";
                                                    if(isset($edit[$fm_control]) && ($edit[$fm_control]==$row->{$value_key} || (is_array($value_array) && in_array($row->{$value_key}, $value_array ) ) ) ){
                                                        $selected="selected";
                                                    }
                                                    echo "<option value='".($row->{$value_key})."' $selected >". $row->{$table_val} ."</option>";
                                                }
                                            }elseif(isset($attributes["values"])){ 
                                                foreach($attributes["values"] as $key => $option){
                                                    $selected = "";
                                                    $selected_values = isset($edit[$fm_control])?explode(",",$edit[$fm_control]):[] ;
                                                    if((isset($edit[$fm_control]) && ($edit[$fm_control]==$key || in_array($key, $selected_values) )) || ( $attributes["value"] == $key  && !isset($edit[$fm_control]))){
                                                        $selected = "selected";
                                                    }
                                                    echo "<option value='$key' $selected>$option</option>";
                                                }
                                            }
                                        ?>
                                    </select>
                                <?php }else if($attributes["type"]=="file") { ?>
                                    <?php echo "<span class='badge badge-info'>".(isset($edit[$fm_control])?$edit[$fm_control]:"")."</span>" ?>
                                  
                                    <?php  $is_multiple =  isset($attributes["multiple"])?"[]":"" ?>
                                    <input  type="<?=$attributes["type"] ?>"  <?= $html_attr ?>name="<?=$fm_control.$is_multiple  ?>">
                                   <?php 
                                }else{?>
                                    <input type="<?=$attributes["type"] ?>"  <?= $html_attr ?> name="<?=$fm_control ?>">
                                <?php } ?>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
                </div>
                <?php
            }
            
        }   ?>
            </div>
            <?= isset($options["custom_fields"])?$options["custom_fields"]:"" ?>
            <?= isset($options["submit_button"])?$options["submit_button"]:'
            <div class="box-footer">
                <input type="submit" class="btn btn-primary" value="Submit">
            </div>' ?>
            
            <?php
    }
}

if(!function_exists("to_html_attr")){
    function to_html_attr($rule){
        $rule = preg_replace("/\/]$/","",$rule);
        $case = preg_split("/\[/",$rule,2);
        switch($case[0]){
            case "required":{
                return ["required"=>"required"];
            break;
            }
            case "numeric":
            case "decimal":{
                return ["type"=>"number"];
            break;
            }
            case "max_length":{
                return ["maxlength"=>$case[1]];
            break;
            }
            case "min_length":{
                return ["minlength"=>$case[1]];
            break;
            }
            case "valid_email":{
                return ["type"=>"email"];
            break;
            }
            case "regex_match":{
                return ["pattern"=>trim($case[1],'/')];
            break;
            }
            case "greater_than":{
                return ["min"=>trim($case[1],'/]')];
            break;
            }
            case "less_than":{
               
                return ["max"=>trim($case[1],'/]')];
            break;
            }
            case "in_list":{
                $list = explode(",",trim($case[1],'/]'));
                $values = array_map(function($val){
                    return strtoupper($val);
                },$list);
                return ["values"=>array_combine($list,$values)];
            break;
            }
            default:{
                return [];
            break;
            }
        }
    }
}

/**
 * prime_mover form manager
 * 
 * values
 * $edit = the id of row from the table to edit
 * 
 * 
 */
if(!function_exists("prime_mover_new")){
    function prime_mover_new($edit = 0,array $table_config=[]){
        $url_prefix = "";
        $ci =& get_instance();
        $table = isset($table_config["config"])?$table_config["config"]:$ci->config->item("form_structure")[$ci->router->class];
        $table_name = isset($table_config["table_name"])?$table_config["table_name"]:$ci->router->class;
        $base= ($base = $ci->config->item("prime_mover_base") )?$base."/":"";
        if(is_numeric($edit) && $edit!=0){  
			$sql = $ci->db->get_where($ci->router->class,["_trash"=>"0","id"=>$edit]);
			if($sql->num_rows()>0){
                $data["edit"] = $sql->result()[0];
                $data["main"] = ( $view_file = $ci->config->item("prime_mover_view_file_create"))?$view_file:"new";
			}else{
				show_404();
			}
        }
        
		if($ci->input->method()=="post"){

            if(isset($table["form_filter_hooks"]["before_validation"])){
                call_user_func($table["form_filter_hooks"]["before_validation"]);
            }
			$fv = $ci->form_validation;
			foreach($table["form"] as $row){
				foreach($row as $key => $element){
					if(is_array($element)){
						$field_name = isset($element["label"])?$element["label"]: $key;
                        $rules = isset($element["rules"])?$element["rules"]:"trim";
                        if(isset($element["parent"])){
                            if(!isset($_POST[$element["parent"][0]])){
                                show_error("Parent element not found for <i>$key</i>");
                            }
                            if($_POST[$element["parent"][0]] !=  $element["parent"][1]){
                                continue;
                            }
                            
                        }
                        $fv->set_rules($key,$field_name,$rules);
                        
					}else{
						$fv->set_rules($element,$element,"trim");
					}
				}
            }
            $after_submit_function_name = isset($table["form_filter_hooks"]["submit_message"])?$table["form_filter_hooks"]["submit_message"]:"alert";
            
			if($ci->form_validation->run()){
                $edit_url = $ci->input->post("id")?"/".$ci->input->post("id"):"";
                if(!empty($_FILES)){
                    foreach($_FILES as $name => $property ){
                        $config = [
                            "upload_path"=>"./assets/uploads",
                            "max_size"=>3000
                        ];
                        foreach($table["form"] as $row){
                            if(isset($row[$name]) && isset($row[$name]["allowed_types"])){
            
                                if(isset($row[$name]["upload_path"])){
                                    $config["upload_path"] = $row[$name]["upload_path"];
                                }
                                $config["allowed_types"]=$row[$name]["allowed_types"];
                                $ci->load->library("upload",$config);
                                
                                if(is_array($property["name"]) && count($property["name"])>0){
                                    foreach($property["name"] as $key => $value){
                                        $_FILES['_dd_file']['name']= $_FILES[$name]['name'][$key];
                                        $_FILES['_dd_file']['type']= $_FILES[$name]['type'][$key];
                                        $_FILES['_dd_file']['tmp_name']= $_FILES[$name]['tmp_name'][$key];
                                        $_FILES['_dd_file']['error']= $_FILES[$name]['error'][$key];
                                        $_FILES['_dd_file']['size']= $_FILES[$name]['size'][$key];
                                        if($ci->upload->do_upload("_dd_file")){
                                            $_POST[$name] .= $ci->upload->data("file_name")."|";
                                        }else{
                                            
                                            if(isset($row[$name]["required"]) && $row[$name]["required"]=="required" ){
                                                call_user_func($after_submit_function_name,"<b>File upload error</b> <br>".$ci->upload->display_errors(),"danger",$base.$url_prefix.$ci->router->class."/".$ci->router->method.$edit_url."?". http_build_query($_POST) );
                                            }
                                        }
                                    }
                                }else{
                                            
                                    if($ci->upload->do_upload($name)){
                                        $_POST[$name] = $ci->upload->data("file_name");
                                        
                                    }else{
                                        // echo "Hi".$name.$ci->upload->display_errors();
                                        if(isset($row[$name]["required"]) && $row[$name]["required"]=="required" ){
                                            call_user_func($after_submit_function_name,"<b>File upload error</b> <br>".$ci->upload->display_errors(),"danger",$base.$url_prefix.$ci->router->class."/".$ci->router->method.$edit_url."?". http_build_query($_POST) );
                                        }
                                    }
                                }
                            }
                        }
                    }

                }
                $_POST = isset($table["form_filter_hooks"]["before_save"])?call_user_func($table["form_filter_hooks"]["before_save"],$_POST):$_POST;
                $xss = isset($table["xss"])?$table["xss"]:true;
                if($ci->input->post("id")){
					if($ci->Db_model->update($table_name,$ci->input->post(null,$xss))){
                        isset($table["form_filter_hooks"]["after_save"])?call_user_func($table["form_filter_hooks"]["after_save"],$ci->input->post()):"";
                        call_user_func($after_submit_function_name,$table_name." has been update","success",base_url($base.$url_prefix).$table_name."/edit/".$ci->input->post("id"),$ci->input->post("id"));
                        
					}else{
						call_user_func($after_submit_function_name,"System Error,<br>Could not update","danger",base_url($base.$url_prefix).$table_name."/".$ci->router->method);
					}
				}else{
                    if($id = $ci->Db_model->save($table_name,$ci->input->post(null,$xss))){
                        $_POST["id"] = $id;
                        isset($table["form_filter_hooks"]["after_save"])?call_user_func($table["form_filter_hooks"]["after_save"],$ci->input->post()):"";
                        call_user_func($after_submit_function_name,$table_name." has been saved","success",$base.$url_prefix.$table_name."/".$ci->router->method,$id);
                        
					}else{
						call_user_func($after_submit_function_name,"System Error,<br>Could not save".$ci->db->last_query(),"danger",$base.$url_prefix.$table_name."/".$ci->router->method."?". http_build_query($_POST) );
					}
				}
			}else{
				call_user_func($after_submit_function_name,validation_errors(),"danger",$base.$url_prefix.$table_name."/".$ci->router->method."?". http_build_query($_POST) );
			}
        }

		$ci->load->view($base.'main', ( isset($data)?$data:"" ) );
    }
}

if(!function_exists("prime_mover_list")){
 /*
 *  var $table = list:array(
 *                          exclude : array(<column names >)
 *                          rename : array(<column_name>=><custom name>)
 *                          join : array(<table name>, <join id column-name>, <column name which is to show instead of id>)
 *                          filter_hooks : array("<hooks_name>" => "<function name>")
 *                                         available hooks: 
 *                                              col_<column_name>:function($data,$id)), 
 *                                              db_where(to add extra where condition):function()
 *                                          
 *                     )
 */   function prime_mover_list($table_config=[],$options=[]){
        $ci =& get_instance();

        $fm_config = $ci->config->item("form_structure");
        $form_structure = isset($fm_config[$ci->router->class])?$fm_config[$ci->router->class]:[];
        $table = count($table_config)<=0?$form_structure:$table_config;
        $base= ($base = $ci->config->item("prime_mover_base") )?$base."/":"";

    
        
        if(isset($table["list"]["index"])){
            foreach($table["list"]["index"] as $key => $val){
                // debug(array_splice($fields,2,4));

            }   
        }
        if(isset($options["class"])){
            $data["class_name"] = $options["class"];
        }
        if(isset($options["method"])){
            $data["main"] = $options["method"];
        }
        // debug($fields);
        $data["body"] =isset($options["body"])?$options["body"]:[];
        $data["body_only"] = isset($options["body_only"]) && $options["body_only"] ? true: false;
        $base= ($base = $ci->config->item("prime_mover_base") )?$base."/":"";
		$ci->load->view($base.'main', ( isset($data)?$data:"" ) );
    }
}

function get_the_table($class_name=null,$options=[]){
    $ci = & get_instance();
    $class_name = empty($class_name)?$ci->router->class:$class_name;
    $table_data = isset($options["table"]) ? $options["table"] : $ci->config->item("form_structure")[$class_name];
    $url = isset($table_data["list"]["data-url"])?$table_data["list"]["data-url"]: base_url('tables/get_table_data/'.$class_name);
    $url = isset($options["data-url"])?$options["data-url"]:$url;

    $fields = isset($table_data["list"]["fields"])?$table_data["list"]["fields"]: $ci->db->list_fields($class_name);
    
    if(isset($table_data["list"]["exclude"]) && is_array($table_data["list"]["exclude"])){
        $fields = array_diff($fields,$table_data["list"]["exclude"]);
    }
    if(isset($table_data["list"]["join_column"])){
        if(is_array($table_data["list"]["join_column"]["select"])){
            foreach($table_data["list"]["join_column"]["select"] as $new_col){
                preg_match('/(\w+)$/',$new_col,$mathch);
                $fields[] = $mathch[0];
            }
        }else{
            preg_match('/(\w+)$/',$table_data["list"]["join_column"]["select"],$mathch);
            $fields[] = $mathch;
        }
    }
?>
<div class="row">
            <div class="col-sm-12">   
                <table id="dataTable" data-url="<?= $url ?>" class=" data-table table table-hover table-striped ">
                    <thead>
                    <?php ob_start() ?>
                        <tr>
                            <?php
                                foreach($fields as $field){
                                    
                                    $jump = true;
                                    $exclude = [];
                                    $realName = $field;
                                    $exclude = isset($table_data["list"]["exclude"]) ? $table_data["list"]["exclude"]:[];
                                    if(isset($table_data)){
                                        $jump = false;
                                        if(isset($table_data["list"]["rename"])){
                                            if(array_key_exists($field,$table_data["list"]["rename"])){
                                                $field = $table_data["list"]["rename"][$field];
                                            }
                                        }
                                    }
                                  
                                    if(!in_array($field,$exclude) || $jump){
                                        echo "
                                        <th>".( ucfirst(str_replace("_"," ",$field)) )."
                                        </th>";
                                    }
                                    
                                    
                                }
                            ?>
                            <?php 
                            if(!in_array("actions",$table_data["list"]["exclude"])): ?>

                            <th>Actions
                            </th>
                            <?php endif; ?>
                        </tr>
                        <?php $head_tr = ob_get_contents(); ?> 
                    </thead>
                    <tbody>
                    <?php
                        
                    ?>
                    </tbody>
                    <tfoot>
                    <?= $head_tr ?>
                    </tfoot>
                </table>
            </div>
        </div>
<?php
}
if(!function_exists("alert")){
    function alert($msg,$cls="",$redirection=""){
        $ci =& get_instance();
       if($ci->input->is_ajax_request()){
           $header_code = 500;
           switch($cls){
               case "success":
                $header_code= 200;
               break;
               case "danger":
                $header_code = 422;
                break;
                case "info":
                    $header_code = 200;
                break;
           }
        $ci->output
        ->set_status_header($header_code)
        ->set_content_type('application/json', 'utf-8')
        ->set_output(json_encode(["message"=>$msg,"status"=>$cls]))
        ->_display();
        die;
       }else{
            $_SESSION["alerts"] = isset($_SESSION["alerts"])?$_SESSION["alerts"]:[];
            $_SESSION["alerts"][] = ["message"=>$msg,"class"=>$cls];
            
            if(!empty($redirection)){
                redirect($redirection);
            }
       }
    }
}
function debug($data,$die= true){
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    $debug = debug_backtrace();
    echo "<b>{$debug[0]['file']} {$debug[0]['line']}</b>";
    $die and die();
}
function print_json(array $data, object $that=null){
    if($that===null){
        $that =& get_instance();
    }

    $that->output->set_content_type('application/json')->set_status_header(200)->set_output(json_encode($data))->_display();
    die;
}