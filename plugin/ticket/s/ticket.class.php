<?php

class TicketManager {
	function TicketManager(){		
	}
	public static function get_all_tickets_categories(){
		$table_support_category = Database::get_main_table(TABLE_SUPPORT_CATEGORY);
		$table_support_project = Database::get_main_table(TABLE_SUPPORT_PROJECT);
		$sql = "SELECT category.*, project.other_area , project.email FROM ".$table_support_category."  category,".$table_support_project." project WHERE project.project_id = category.project_id ORDER BY category.total_tickets DESC;";
		$result = Database::query($sql);
		$types = array();
		while  ($row = Database::fetch_assoc($result)) {
			$types[]=$row;
		}
		return $types;
	}
	public static function get_all_tickets_status(){
		$table_support_status = Database::get_main_table(TABLE_SUPPORT_STATUS);
		$sql = "SELECT * FROM ".$table_support_status;
		$result = Database::query($sql);
		$types = array();
		while  ($row = Database::fetch_assoc($result)) {
			$types[]=$row;
		}
		return $types;
	}
	public static function insert_new_ticket($category_id, $course_id,$project_id,$other_area, $email,$subject, $content,$personalemail="",$file_attachments,$source='VRT',$priority='NRM',$status='',$request_user ='',$assigned_user=0){
		$table_support_tickets = Database::get_main_table(TABLE_SUPPORT_TICKET);
		$table_support_category = Database::get_main_table(TABLE_SUPPORT_CATEGORY);
		$now = api_get_utc_datetime();
		$user_id = api_get_user_id();
		if($status == ''){
			$status = NEWTCK;
			if($other_area > 0){
				$status = REENVIADO;
			}
		}

		if($request_user == ''&& $source == 'VRT'){ 
			$request_user = $user_id;
		}
		$course_id = intval($course_id);
		$sql_insert_ticket= "INSERT INTO ".$table_support_tickets." (project_id,category_id,priority_id,course_id, request_user, personal_email ,status_id, start_date,sys_insert_user_id,sys_insert_datetime,sys_lastedit_user_id,sys_lastedit_datetime,source)
		VALUES ('$project_id','$category_id','$priority','$course_id','$request_user','$personalemail','$status','".$now."',$user_id,'".$now."','$user_id','".$now."','$source')";
		$result = Database::query($sql_insert_ticket);
		$ticket_id = Database::insert_id();
		if ( $assigned_user!= 0){
			self::assign_ticket_user($ticket_id, $assigned_user);
		}
		if($ticket_id != 0){
			$ticket_code ="A".str_pad((int) $ticket_id,11,"0",STR_PAD_LEFT);
			$sql_update_code = "UPDATE ".$table_support_tickets." SET ticket_code = '$ticket_code' WHERE ticket_id = '$ticket_id'";
			Database::query($sql_update_code);
			$data_files = array();
			$sql_update_total = "UPDATE ".$table_support_category." SET total_tickets = total_tickets +1 WHERE category_id = '$category_id';";
			Database::query($sql_update_total);
			if(self::insert_message($ticket_id, $subject, $content,$file_attachments, $request_user)){
				global $data_files;
				if($other_area){
					$user = UserManager::get_user_info_by_id($request_user);
					$mensaje_helpdesk = '<table>
											<tr>
												<td width="100px"><b>Cliente:</b></td>
												<td width="400px">'.$user['firstname'].' '.$user['lastname'].'</td>
											</tr>
											<tr>
												<td width="100px"><b>Usuario:</b></td>
												<td width="400px">'.$user['username'].'</td>
											</tr>
											<tr>
												<td width="100px"><b>Fecha:</b></td>
												<td width="400px">'.api_convert_and_format_date($now, DATE_TIME_FORMAT_LONG).'</td>
											</tr>
											<tr>
												<td width="100px"><b>Asunto:</b></td>
												<td width="400px">'.$subject.'</td>
											</tr>
											<tr>
												<td width="100px"><b>Descripci&oacute;n:</b></td>
												<td width="400px">'.$content.'</td>
											</tr>
										</table>';
					api_mail_html('Soporte virtual', $email, "[SOPORTE] Incidente Reenviado de Soporte Virtual", $mensaje_helpdesk,$user['firstname'].' '.$user['lastname'], $personalemail, array('cc'=>'soportevirtual@usil.edu.pe'), $data_files);
					$mensaje_alumno ='<p>Su consulta fue reenviada al area responsable : <a href="mailto:'.$email.'">'.$email.'</a></p>';
					$mensaje_alumno .='<p>La respuesta a su consulta ser&aacute; enviada al correo  : <a href="#">'.$personalemail.'</a></p>';
					self::insert_message($ticket_id, "Mensaje Reenviado", $mensaje_alumno, null, 1);
				}
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
	public static function insert_message($ticket_id,$subject,$content,$file_attachments,$user_id,$status='NOL',$envioconfirmacion=false){
		global $data_files;
		$table_support_messages = Database::get_main_table(TABLE_SUPPORT_MESSAGE);
		$table_support_tickets = Database::get_main_table(TABLE_SUPPORT_TICKET);
		$table_support_message_attachments = Database::get_main_table(TABLE_SUPPORT_MESSAGE_ATTACHMENTS);
		if ($envioconfirmacion){
			$formulario = '<form action="ticket_details.php?ticket_id='.$ticket_id.'" id="confirmticket" method="POST" >
							<p>Esta respuesta le result&oacute; satisfactoria</p>
							<input id="respuestasi" type="submit" value="si" name="respuesta" />
							<input id="respuestano" type="submit" value="no" name="respuesta" />
						   </form>';
			$content.=$formulario;
			Database::query("UPDATE $table_support_tickets SET status_id='XCF' WHERE ticket_id = '$ticket_id'");
		}
		$sql_message_id = "SELECT COUNT(*) as total_messages FROM ".$table_support_messages." WHERE ticket_id ='$ticket_id'";
		$result = Database::query($sql_message_id);
		$obj = Database::fetch_object($result);
		$message_id = $obj->total_messages+1;
		$now = api_get_utc_datetime();
		$sql_insert_message = "INSERT INTO ".$table_support_messages." (ticket_id,message_id,subject, message,ip_address,sys_insert_user_id,sys_insert_datetime,sys_lastedit_user_id,sys_lastedit_datetime,status)
		VALUES ('$ticket_id','$message_id','$subject','$content','".$_SERVER['REMOTE_ADDR']."','$user_id','".$now."','$user_id','".$now."','$status');";
		Database::query($sql_insert_message);
		$sql_update_total_message ="UPDATE ".$table_support_tickets." SET sys_lastedit_user_id ='$user_id' , sys_lastedit_datetime ='".$now."' , total_messages = ("."SELECT COUNT(*) as total_messages FROM ".$table_support_messages." WHERE ticket_id ='$ticket_id'".") WHERE ticket_id ='$ticket_id' ";
		Database::query($sql_update_total_message);
		$sql_message_att_id = "SELECT COUNT(*) as total_attach FROM ".$table_support_message_attachments." WHERE ticket_id ='$ticket_id' AND message_id = '$message_id'";
		$result = Database::query($sql_message_att_id);
		$obj = Database::fetch_object($result);
		$message_attch_id = $obj->total_attach+1;
		if (is_array($file_attachments)) {
			foreach ($file_attachments as $file_attach) {				
				if ($file_attach['error'] == 0) {
					$data_files[] = self::save_message_attachment_file($file_attach,$ticket_id,$message_id,$message_attch_id);
					$message_attch_id++;
				}else{
					if($file_attach['error']!=UPLOAD_ERR_NO_FILE )return false;
				}
			}
		}
		return true;
		
		
	}
	public static function save_message_attachment_file($file_attach,$ticket_id,$message_id,$message_attch_id) {
		$now = api_get_ut;
		$user_id = api_get_user_id();
		$new_file_name = add_ext_on_mime(stripslashes($file_attach['name']), $file_attach['type']);
		$file_name =$file_attach['name'];
		$table_support_message_attachments = Database::get_main_table(TABLE_SUPPORT_MESSAGE_ATTACHMENTS);
		if (!filter_extension($new_file_name))  {
			Display :: display_error_message(get_lang('UplUnableToSaveFileFilteredExtension'));
		} else {
			$new_file_name = uniqid('');
			$path_attachment = api_get_path(SYS_PATH);
			$path_message_attach =$path_attachment.'tck_messageattch/';
			if (!file_exists($path_message_attach)) {
				@mkdir($path_message_attach, api_get_permissions_for_new_directories(), true);
			}
			$new_path=$path_message_attach.$new_file_name;
			if (is_uploaded_file($file_attach['tmp_name'])) {
				$result= @copy($file_attach['tmp_name'], $new_path);
			}
			$safe_file_comment= Database::escape_string($file_comment);
			$safe_file_name = Database::escape_string($file_name);
			$safe_new_file_name = Database::escape_string($new_file_name);
			$sql="INSERT INTO ".$table_support_message_attachments."(filename, path,ticket_id,message_id,message_attch_id,size,sys_insert_user_id,sys_insert_datetime,sys_lastedit_user_id,sys_lastedit_datetime)
			VALUES ( '$safe_file_name',  '$safe_new_file_name' , '$ticket_id','$message_id', '$message_attch_id','".$file_attach['size']."','$user_id','".$now."','$user_id','".$now."' )";
			$result=Database::query($sql);
			return array('path' => $path_message_attach.$safe_new_file_name,'filename' => $safe_file_name);
		}
	}
	public static function get_tickets_by_user_id($from, $number_of_items, $column, $direction ,$user_id=null){
		$table_support_category = Database::get_main_table(TABLE_SUPPORT_CATEGORY);
		$table_support_tickets = Database::get_main_table(TABLE_SUPPORT_TICKET);
		$table_support_priority = Database::get_main_table(TABLE_SUPPORT_PRIORITY);
		$table_support_status = Database::get_main_table(TABLE_SUPPORT_STATUS);
		$table_support_messages = Database::get_main_table(TABLE_SUPPORT_MESSAGE);
		$table_main_user = Database::get_main_table(TABLE_MAIN_USER);
		$table_main_admin = Database::get_main_table(TABLE_MAIN_ADMIN);
		if(is_null($direction)) $direction ="DESC";
		if(is_null($user_id) || $user_id==0){
			$user_id = api_get_user_id();
		}
		
		$isAdmin = UserManager::is_admin($user_id);
		$sql = "SELECT ticket.*,  ticket.ticket_id AS col0,ticket.start_date AS col1, ticket.sys_lastedit_datetime AS col2 ,cat.name AS col3,user.username AS col4, priority.priority AS col5 ,
				priority.priority  AS col6, status.name AS col7 , ticket.total_messages AS col8, msg.message AS col9, ticket.request_user AS user_id , ticket.assigned_last_user AS responsable 
				FROM ".$table_support_tickets." ticket ,".$table_support_category." cat , ".$table_support_priority ." priority, ".$table_support_status ." status , ".Database::get_main_table(TABLE_MAIN_USER)." user, tck_message msg 
				WHERE cat.category_id = ticket.category_id AND ticket.priority_id = priority.priority_id AND ticket.status_id = status.status_id  AND user.user_id = ticket.request_user
				AND ticket.ticket_id= msg.ticket_id AND message_id=1 ";
		if(!$isAdmin){
			$sql.=" AND request_user = '$user_id' ";
		}
		$keyword_unread = Database::escape_string(trim($_GET['keyword_unread']));
		//Search simple
		if(isset($_GET['submit_simple'])){
			if($_GET['keyword']!=''){
				$keyword = Database::escape_string(trim($_GET['keyword']));
				$sql.=" AND (ticket.ticket_code = '".$keyword."' OR ticket.ticket_id = '".$keyword."' OR user.firstname LIKE '%".$keyword."%' OR user.lastname LIKE '%".$keyword."%'  OR concat(user.firstname,' ',user.lastname) LIKE '%".$keyword."%'  OR concat(user.lastname,' ',user.firstname) LIKE '%".$keyword."%' OR user.username LIKE '%".$keyword."%')  ";
			}
		}
		//Search advanced
		if(isset($_GET['submit_advanced'])){
			$keyword_category = Database::escape_string(trim($_GET['keyword_category']));
			$keyword_request_user = Database::escape_string(trim($_GET['keyword_request_user']));
			$keyword_admin = Database::escape_string(trim($_GET['keyword_admin']));
			$keyword_start_date_start = Database::escape_string(trim($_GET['keyword_start_date_start']));		
			$keyword_start_date_end = Database::escape_string(trim($_GET['keyword_start_date_end']));
			$keyword_status = Database::escape_string(trim($_GET['keyword_status']));
			$keyword_source = Database::escape_string(trim($_GET['keyword_source']));
			$keyword_priority = Database::escape_string(trim($_GET['keyword_priority']));
			$keyword_range = Database::escape_string(trim($_GET['keyword_dates']));
			$keyword_course = Database::escape_string(trim($_GET['keyword_course']));

			if($keyword_category !=''){
				$sql.=" AND ticket.category_id = '$keyword_category'  ";				
			}
			if($keyword_request_user !=''){
				$sql.=" AND (ticket.request_user = '$keyword_request_user' OR user.firstname LIKE '%".$keyword_request_user."%' OR user.official_code LIKE '%".$keyword_request_user."%' OR user.lastname LIKE '%".$keyword_request_user."%'  OR concat(user.firstname,' ',user.lastname) LIKE '%".$keyword_request_user."%'  OR concat(user.lastname,' ',user.firstname) LIKE '%".$keyword_request_user."%' OR user.username LIKE '%".$keyword_request_user."%') ";
			}
			if($keyword_admin !=''){
				$sql.=" AND ticket.assigned_last_user = '$keyword_admin'  ";				
			}
			if($keyword_status !=''){
				$sql.=" AND ticket.status_id = '$keyword_status'  ";				
			}	
			if($keyword_range == '' && $keyword_start_date_start!=''){
				$sql.= " AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') = '$keyword_start_date_start' ";
			}
			if ($keyword_range == '1' && $keyword_start_date_start!='' && $keyword_start_date_end !=''){
				$sql.= " AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') >= '$keyword_start_date_start' AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') <= '$keyword_start_date_end'";
			}
			if ($keyword_priority != ''){
				$sql.=" AND ticket.priority_id = '$keyword_priority'  ";				
			}
			if($keyword_source != ''){
				$sql.= " AND ticket.source = '$keyword_source' ";
			}
			if($keyword_priority != ''){
				$sql.= " AND ticket.priority_id = '$keyword_priority' ";
			}
			if ($keyword_course != ''){
				$course_table = Database :: get_main_table(TABLE_MAIN_COURSE);
				$sql .= " AND ticket.course_id IN ( ";
				$sql .= "SELECT id FROM $course_table WHERE (title LIKE '%".$keyword_course."%' OR code LIKE '%".$keyword_course."%' OR visual_code LIKE '%".$keyword_course."%' )) ";
			}

		}
		if($keyword_unread == 'yes'){
			$sql.= " AND ticket.ticket_id IN (SELECT ticket.ticket_id FROM  $table_support_tickets ticket,  $table_support_messages message,  $table_main_user user WHERE ticket.ticket_id = message.ticket_id   AND message.status = 'NOL'   AND message.sys_insert_user_id = user.user_id   AND user.user_id NOT IN (SELECT user_id FROM $table_main_admin)    AND ticket.status_id != 'REE'   GROUP BY ticket.ticket_id)";
		}else if($keyword_unread == 'no'){
			$sql.= " AND ticket.ticket_id NOT IN (SELECT ticket.ticket_id FROM  $table_support_tickets ticket,  $table_support_messages message,  $table_main_user user WHERE ticket.ticket_id = message.ticket_id   AND message.status = 'NOL'   AND message.sys_insert_user_id = user.user_id   AND user.user_id NOT IN (SELECT user_id FROM $table_main_admin)   AND ticket.status_id != 'REE'   GROUP BY ticket.ticket_id)";
		}
		$sql .= " ORDER BY col$column $direction";
		$sql .= " LIMIT $from,$number_of_items";
		$result = Database::query($sql);
		$tickets = array();
		while ($row = Database::fetch_assoc($result)){
			$sql_unread = "SELECT COUNT(DISTINCT message.message_id) AS unread FROM ".$table_support_tickets."  ticket, ".$table_support_messages." message, ".$table_main_user." user
 			WHERE ticket.ticket_id = message.ticket_id AND ticket.ticket_id= '".$row['col0']."' AND message.status='NOL' AND message.sys_insert_user_id = user.user_id ";
			if($isAdmin){
				$sql_unread .=" AND user.user_id  NOT IN (SELECT user_id FROM $table_main_admin)   AND ticket.status_id != 'REE' ";
			}else{				
				$sql_unread .=" AND user.user_id IN (SELECT user_id FROM $table_main_admin)   ";
			}
			$result_unread = Database::query($sql_unread);
			$unread = Database::fetch_object($result_unread)->unread;
			$userinfo = UserManager::get_user_info_by_id($row['user_id']);
			$name = '<a href="'.api_get_path(WEB_PATH).'main/admin/user_information.php?user_id='.$row['user_id'].'">'.api_get_person_name($userinfo['firstname'], $userinfo['lastname']).'</a>';
			$actions = "";			
			/*if($row['status_id']!='CLS' && $row['status_id']!='REE'){						
				if( $row['responsable'] != 0 && $row['responsable'] == $user_id ){
					$actions = '<a href="myticket.php?ticket_id='.$row['ticket_id'].'&amp;action=unassign" title="desasignarme"><img src="../../../main/img/admin_star.png" border="0" /></a>';					
				}else{	
					$actions = '<a href="myticket.php?ticket_id='.$row['ticket_id'].'&amp;action=assign" title="asignarme"><img src="../../../main/img/admin_star_na.png" border="0" /></a>';				
				}
			}*/
			if( $row['responsable'] != 0){
				$row['responsable']= api_get_user_info($row['responsable']);
				$row['responsable']= '<a href="'.api_get_path(WEB_PATH).'main/admin/user_information.php?user_id='.$row['responsable']['user_id'].'">'.$row['responsable']['firstname'].' '.$row['responsable']['lastname'].'</a>';
			}else if($row['status_id']!='REE'){
				$row['responsable'] = '<span style="color:#ff0000;">Por Asignar</span>';
			}else{
				$row['responsable'] = '<span style="color:#00ff00;">REENVIADO</span>';
			}
			switch ($row['source']){
				case 'PRE':
					$img_source = '../img/icons/32/user.png';
					break;
				case 'MAI':
					$img_source = '../img/icons/32/mail.png';
					break;
				case 'TEL':
					$img_source = '../img/icons/32/event.png';
					break;
				default:
					$img_source = '../img/icons/32/course_home.png';
					break;
			}
			$row['col1'] = api_get_local_time($row['col1']);
			$row['col2'] = api_get_local_time($row['col2']);
			if($isAdmin){
				$actions .= '<a href="ticket_details.php?ticket_id='.$row['col0'].'">'.Display::return_icon('synthese_view.gif', get_lang('Info')).'</a>&nbsp;&nbsp;';
				if($row['priority_id']=='ALT' && $row['status_id']!='CLS') $actions .= '<img src="../../../main/img/exclamation.png" border="0" />';
				$row['col0'] = Display::return_icon($img_source, get_lang('Info')).'<a href="ticket_details.php?ticket_id='.$row['col0'].'">'.$row['ticket_code'].'</a>';
				if($row['col7']=='PENDIENTE'){
					$row['col7'] = '<span style="color: #f00; font-weight:bold;">'.$row['col7'].'</span>';
				}
				//programa: $userinfo['extra']['programa']
				$ticket=array($row['col0'],api_format_date($row['col1'],'%d/%m/%y - %I:%M:%S %p'),api_format_date($row['col2'],'%d/%m/%y - %I:%M:%S %p'),$row['col3'],$name,$row['responsable'],$row['col7'],$row['col8'],$actions, eregi_replace("[\n|\r|\n\r|\r\n]", ' ', strip_tags($row['col9'])) );
			}else{
				$actions ="";
				$actions .= '<a href="ticket_details.php?ticket_id='.$row['col0'].'">'.Display::return_icon('synthese_view.gif', get_lang('Info')).'</a>&nbsp;&nbsp;';
				$row['col0'] =Display::return_icon($img_source, get_lang('Info')).'<a href="ticket_details.php?ticket_id='.$row['col0'].'">'.$row['ticket_code'].'</a>';
				$now = api_strtotime(api_get_utc_datetime());
				$last_edit_date = api_strtotime($row['sys_lastedit_datetime']);
				$dif = $now - $last_edit_date ;

				if($dif > 172800 && $row['priority_id'] == 'NRM' && $row['status_id']!='CLS'){
					$actions .= '<a href="myticket.php?ticket_id='.$row['ticket_id'].'&amp;action=alert"><img src="../../../main/img/exclamation.png" border="0" /></a>';
				}
				if($row['priority_id']=='ALT') $actions .= '<img src="../../../main/img/admin_star.png" border="0" />';
				$ticket=array($row['col0'],api_format_date($row['col1'],'%d/%m/%y - %I:%M:%S %p'),api_format_date($row['col2'],'%d/%m/%y - %I:%M:%S %p'),$row['col3'],$row['col7'],$actions);
			}
			if($unread > 0){

				$ticket['0']=$ticket['0'].'&nbsp;&nbsp;('.$unread.')<a href="ticket_details.php?ticket_id='.$row['ticket_id'].'"><img src="../../../main/img/message_new.png" border="0" title="'.$unread.' Nuevo(s) Mensajes"/></a>';

			}
			if ($isAdmin)
			$ticket['0'].= '&nbsp;&nbsp;<a  href="javascript:void(0)" onclick="load_history_ticket(\'div_'.$row['ticket_id'].'\','.$row['ticket_id'].')">
					<img onclick="load_course_list(\'div_'.$row['ticket_id'].'\','.$row['ticket_id'].')" onmouseover="clear_course_list (\'div_'.$row['ticket_id'].'\')" src="../../../main/img/history.gif" title="'.get_lang('Historial').'" alt="'.get_lang('Historial').'"/>
					<div class="blackboard_hide" id="div_'.$row['ticket_id'].'">&nbsp;&nbsp;</div>
					</a>&nbsp;&nbsp;';
			$tickets[] = $ticket;
		}
		return $tickets;
	}
	public static function get_total_tickets_by_user_id($user_id=null){
		$table_support_category = Database::get_main_table(TABLE_SUPPORT_CATEGORY);
		$table_support_tickets = Database::get_main_table(TABLE_SUPPORT_TICKET);
		$table_support_priority = Database::get_main_table(TABLE_SUPPORT_PRIORITY);
		$table_support_status = Database::get_main_table(TABLE_SUPPORT_STATUS);
		$table_support_messages = Database::get_main_table(TABLE_SUPPORT_MESSAGE);
		$table_main_user = Database::get_main_table(TABLE_MAIN_USER);
		$table_main_admin = Database::get_main_table(TABLE_MAIN_ADMIN);
		if(is_null($user_id) || $user_id==0){
			$user_id = api_get_user_id();
		}
		$sql = "SELECT COUNT(ticket.ticket_id) AS total	FROM ".$table_support_tickets." ticket ,".$table_support_category." cat , ".$table_support_priority ." priority, ".$table_support_status ." status , ".Database::get_main_table(TABLE_MAIN_USER)." user
				WHERE cat.category_id = ticket.category_id AND ticket.priority_id = priority.priority_id AND ticket.status_id = status.status_id  AND user.user_id = ticket.request_user ";
		if(!api_is_platform_admin()){
			$sql.=" AND request_user = '$user_id' ";
		}

		//Search simple
		if(isset($_GET['submit_simple'])){
			if($_GET['keyword']!=''){
				$keyword = Database::escape_string(trim($_GET['keyword']));
				$sql.=" AND (ticket.ticket_code = '".$keyword."' OR user.firstname LIKE '%".$keyword."%' OR user.lastname LIKE '%".$keyword."%'  OR concat(user.firstname,' ',user.lastname) LIKE '%".$keyword."%'  OR concat(user.lastname,' ',user.firstname) LIKE '%".$keyword."%' OR user.username LIKE '%".$keyword."%')  ";
			}
		}
		$keyword_unread = Database::escape_string(trim($_GET['keyword_unread']));
		//Search advanced
		if(isset($_GET['submit_advanced'])){
			$keyword_category = Database::escape_string(trim($_GET['keyword_category']));
			$keyword_request_user = Database::escape_string(trim($_GET['keyword_request_user']));
			$keyword_admin = Database::escape_string(trim($_GET['keyword_admin']));
			$keyword_start_date_start = Database::escape_string(trim($_GET['keyword_start_date_start']));
			$keyword_start_date_end = Database::escape_string(trim($_GET['keyword_start_date_end']));
			$keyword_status = Database::escape_string(trim($_GET['keyword_status']));
			$keyword_source = Database::escape_string(trim($_GET['keyword_source']));
			$keyword_priority = Database::escape_string(trim($_GET['keyword_priority']));
			$keyword_range = Database::escape_string(trim($_GET['keyword_dates']));
			$keyword_course = Database::escape_string(trim($_GET['keyword_course']));				

			if($keyword_category !=''){
				$sql.=" AND ticket.category_id = '$keyword_category'  ";
			}
			if($keyword_request_user !=''){
			$sql.=" AND (ticket.request_user = '$keyword_request_user' OR user.firstname LIKE '%".$keyword_request_user."%' OR user.official_code LIKE '%".$keyword_request_user."%' OR user.lastname LIKE '%".$keyword_request_user."%'  OR concat(user.firstname,' ',user.lastname) LIKE '%".$keyword_request_user."%'  OR concat(user.lastname,' ',user.firstname) LIKE '%".$keyword_request_user."%' OR user.username LIKE '%".$keyword_request_user."%') ";
			}
				if($keyword_admin !=''){
				$sql.=" AND ticket.assigned_last_user = '$keyword_admin'  ";
			}
			if($keyword_status !=''){
			$sql.=" AND ticket.status_id = '$keyword_status'  ";
			}
			if($keyword_range == '' && $keyword_start_date_start!=''){
			$sql.= " AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') = '$keyword_start_date_start' ";
			}
			if ($keyword_range == '1' && $keyword_start_date_start!='' && $keyword_start_date_end !=''){
					$sql.= " AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') >= '$keyword_start_date_start' AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') <= '$keyword_start_date_end'";
			}
							if ($keyword_priority != ''){
			$sql.=" AND ticket.priority_id = '$keyword_priority'  ";
			}
			if($keyword_source != ''){
			$sql.= " AND ticket.source = '$keyword_source' ";
			}
			if($keyword_priority != ''){
			$sql.= " AND ticket.priority_id = '$keyword_priority' ";
			}
			if ($keyword_course != ''){
				$course_table = Database :: get_main_table(TABLE_MAIN_COURSE);
				$sql .= " AND ticket.course_id IN ( ";
				$sql .= "SELECT id FROM $course_table WHERE (title LIKE '%".$keyword_course."%' OR code LIKE '%".$keyword_course."%' OR visual_code LIKE '%".$keyword_course."%' )) ";
			}

		}
		if($keyword_unread == 'yes'){
			$sql.= " AND ticket.ticket_id IN (SELECT ticket.ticket_id FROM  $table_support_tickets ticket,  $table_support_messages message,  $table_main_user user WHERE ticket.ticket_id = message.ticket_id   AND message.status = 'NOL'   AND message.sys_insert_user_id = user.user_id   AND user.user_id NOT IN (SELECT user_id FROM $table_main_admin)    AND ticket.status_id != 'REE'   GROUP BY ticket.ticket_id)";
		}else if($keyword_unread == 'no'){
			$sql.= " AND ticket.ticket_id NOT IN (SELECT ticket.ticket_id FROM  $table_support_tickets ticket,  $table_support_messages message,  $table_main_user user WHERE ticket.ticket_id = message.ticket_id   AND message.status = 'NOL'   AND message.sys_insert_user_id = user.user_id   AND user.user_id NOT IN (SELECT user_id FROM $table_main_admin)   AND ticket.status_id != 'REE'   GROUP BY ticket.ticket_id)";
		}
		$res = Database::query($sql);
		$obj = Database::fetch_object($res);
		return $obj->total;
	}
	public static function get_ticket_detail_by_id($ticket_id,$user_id){
		$table_support_category = Database::get_main_table(TABLE_SUPPORT_CATEGORY);
		$table_support_tickets = Database::get_main_table(TABLE_SUPPORT_TICKET);
		$table_support_priority = Database::get_main_table(TABLE_SUPPORT_PRIORITY);
		$table_support_status = Database::get_main_table(TABLE_SUPPORT_STATUS);
		$table_support_messages = Database::get_main_table(TABLE_SUPPORT_MESSAGE);
		$table_support_message_attachments = Database::get_main_table(TABLE_SUPPORT_MESSAGE_ATTACHMENTS);

		$sql =  "SELECT ticket.* ,cat.name , status.name as status, priority.priority FROM ".$table_support_tickets." ticket, ".$table_support_category." cat ,".$table_support_priority." priority , ".$table_support_status." status 
		WHERE ticket.ticket_id = '$ticket_id' AND cat.category_id = ticket.category_id AND priority.priority_id = ticket.priority_id AND status.status_id = ticket.status_id  ";
		if(!UserManager::is_admin($user_id)){
			$sql.= "AND ticket.request_user = '$user_id'";
		}
		$result = Database::query($sql);
		$ticket = array();
		if(Database::num_rows($result)>0){
			while ($row = Database::fetch_assoc($result)){
				$row['course'] =  null;
				$row['start_date'] = api_convert_and_format_date(api_get_local_time($row['start_date']), DATE_TIME_FORMAT_LONG,_api_get_timezone());
				$row['end_date'] = api_convert_and_format_date(api_get_local_time($row['end_date']), DATE_TIME_FORMAT_LONG,_api_get_timezone());
				$row['sys_lastedit_datetime'] = api_convert_and_format_date(api_get_local_time($row['sys_lastedit_datetime']), DATE_TIME_FORMAT_LONG,_api_get_timezone());
				$row['course_url'] = null;
				if($row['course_id']!=0){
					$course = api_get_course_info_by_id($row['course_id']);
					$row['course_url']	= '<a href="'.api_get_path(WEB_COURSE_PATH).$course['path'].'">'.$course['name'].'</a>';
				}
				$userinfo = api_get_user_info($row['request_user']);
				$row['user_url'] = '<a href="'.api_get_path(WEB_PATH).'main/admin/user_information.php?user_id='.$row['request_user'].'">'.api_get_person_name($userinfo['firstname'], $userinfo['lastname']).'</a>';
				$ticket['usuario'] = $userinfo;
				$ticket['ticket']= $row;
			}
			$sql = "SELECT  * FROM ".$table_support_messages." message, ".Database::get_main_table(TABLE_MAIN_USER)." user  WHERE message.ticket_id = '$ticket_id' AND message.sys_insert_user_id = user.user_id ";
			$result = Database::query($sql);
			$ticket['messages']= array();
			$attach_icon = Display::return_icon('attachment.gif', '');
			$admin_table = Database::get_main_table(TABLE_MAIN_ADMIN);
			while ($row = Database::fetch_assoc($result)){
				$message = $row;				
				//Check if user is an admin
				$sql_admin = "SELECT user_id FROM $admin_table
				WHERE user_id = '".intval($message['user_id'])."' LIMIT 1";
				$result_admin = Database::query($sql_admin);
				$message['admin'] = false;
				if (Database::num_rows($result_admin) > 0) {
				$message['admin'] = true;
				}
				$message['user_created']= '<a href="'.api_get_path(WEB_PATH).'main/admin/user_information.php?user_id='.$row['user_id'].'">'.$row['firstname'].' '.$row['lastname'].'</a>';
				$sql_atachment ="SELECT * FROM ".TABLE_SUPPORT_MESSAGE_ATTACHMENTS." WHERE message_id = ".$row['message_id']." AND ticket_id= '$ticket_id'  ";
				$result_attach = Database::query($sql_atachment);
				while ($row2 = Database::fetch_assoc($result_attach)){
					$archiveURL =$archiveURL=api_get_path(WEB_PLUGIN_PATH).PLUGIN_NAME.'/s/download.php?ticket_id='.$ticket_id.'&file=';
					$row2['attachment_link']= 	$attach_icon.'&nbsp;<a href="'.$archiveURL.$row2['path'].'&title='.$row2['filename'].'">'.$row2['filename'].'</a>&nbsp;('.$row2['size'].')';
					$message['atachments'][]= $row2;
				}
				$ticket['messages'][] = $message;
			}
		}
		return $ticket;	
	}
	public static function assign_ticket_user($ticket_id, $user_id){
		$table_support_tickets = Database::get_main_table(TABLE_SUPPORT_TICKET);
		$table_support_assigned_log = Database::get_main_table(TABLE_SUPPORT_ASSIGNED_LOG);
		$now = api_get_utc_datetime();			
		$sql_update = "UPDATE ".$table_support_tickets." SET  assigned_last_user = '$user_id'  WHERE ticket_id = '$ticket_id' ";
		Database::query($sql_update);
		if(Database::affected_rows()>0){
			$insert_id = api_get_user_id();
			$sql ="INSERT INTO ".$table_support_assigned_log." (ticket_id , user_id , assigned_date , sys_insert_user_id) VALUES ('$ticket_id','$user_id','".$now."','$insert_id');";
			Database::query($sql);
			if ($insert_id != $user_id){
				$info = api_get_user_info($user_id);
				$mensaje = '<p>Estimado(a):</p><p>'.$info['firstname']." ".$info['lastname']."</p>
							<p>Te asignaron el ticket $ticket_id ".'<a href="'.api_get_path(WEB_PLUGIN_PATH).PLUGIN_NAME.'/s/ticket_details.php?ticket_id='.$ticket_id.'">Ticket</a></p>
							<p>Centro de Educaci&oacute;n Virtual</p>';
				api_mail_html($info['firstname']." ".$info['lastname'], $info['mail'],  "[TICKETS] Asignacion de Ticket #$ticket_id ", $mensaje,'Plataforma Virtual', 'plataformavirtual@usil.edu.pe', array('cc'=>'plataformavirtual@usil.edu.pe'));
			}
		}
	}
	public static function update_message_status($ticket_id,$user_id){
		$table_support_messages = Database::get_main_table(TABLE_SUPPORT_MESSAGE);
		$sql = "UPDATE ".$table_support_messages." SET status = 'LEI' , sys_lastedit_user_id ='".api_get_user_id()."' ,sys_lastedit_datetime ='".$now."' WHERE ticket_id ='$ticket_id' ";
		
		if(api_is_platform_admin()){
			$sql .= " AND sys_insert_user_id = '$user_id'";
		}else{

			$sql .= " AND sys_insert_user_id != '$user_id'";
		}
		Database::query($sql);
		if(Database::affected_rows()>0){
			Database::query("UPDATE ".Database::get_main_table(TABLE_SUPPORT_TICKET)." SET status_id = 'PND'  WHERE ticket_id ='$ticket_id' AND status_id = 'NAT'");
			return true;
		}else{
			return false;
		}	
	}
	public static function update_ticket_status($status_id,$ticket_id, $user_id){
		$table_support_tickets = Database::get_main_table(TABLE_SUPPORT_TICKET);
		$table_support_messages = Database::get_main_table(TABLE_SUPPORT_MESSAGE);
		$now = api_get_utc_datetime();
		$sql = "UPDATE ".$table_support_tickets." SET status_id = '$status_id' , sys_lastedit_user_id ='$user_id' ,sys_lastedit_datetime ='".$now."'  WHERE ticket_id ='$ticket_id'";
		Database::query($sql);
		if(Database::affected_rows()>0){
			return true;
		}else{
			return false;
		}	
	}
	public static function get_number_of_messages(){
		$table_support_tickets = Database::get_main_table(TABLE_SUPPORT_TICKET);
		$table_support_messages = Database::get_main_table(TABLE_SUPPORT_MESSAGE);
		$table_main_user = Database::get_main_table(TABLE_MAIN_USER);
		$table_main_admin = Database::get_main_table(TABLE_MAIN_ADMIN);
		$user_info = api_get_user_info();
		$user_id = $user_info['user_id'];
		$sql ="SELECT COUNT( DISTINCT ticket.ticket_id) AS unread  FROM ".$table_support_tickets." ticket, ".$table_support_messages." message , ".$table_main_user." user WHERE ticket.ticket_id = message.ticket_id AND message.status = 'NOL'    AND user.user_id = message.sys_insert_user_id ";
		if(!api_is_platform_admin()){
			$sql .=" AND ticket.request_user = '$user_id'  AND user_id IN (SELECT user_id FROM $table_main_admin)  ";
		}else{
			$sql .=" AND user_id NOT IN (SELECT user_id FROM $table_main_admin) AND ticket.status_id != 'REE'";
		}
		$sql .= "  AND ticket.project_id != '' ";
		$res = Database::query($sql);
		$obj = Database::fetch_object($res);
		return $obj->unread;

	}
	public static function send_alert($ticket_id,$user_id){
		$table_support_tickets = Database::get_main_table(TABLE_SUPPORT_TICKET);
		$now = api_get_utc_datetime();
		$sql = "UPDATE ".$table_support_tickets." SET priority_id = 'ALT', sys_lastedit_user_id ='$user_id' ,sys_lastedit_datetime ='".$now."' WHERE ticket_id = '$ticket_id'";	
		Database::query($sql);	
	}
	public static function close_ticket($ticket_id, $user_id){
		$table_support_tickets = Database::get_main_table(TABLE_SUPPORT_TICKET);
		$now = api_get_utc_datetime();
		$sql = "UPDATE ".$table_support_tickets." SET status_id = 'CLS' , sys_lastedit_user_id ='$user_id' ,sys_lastedit_datetime ='".$now."' ,end_date ='".$now."' WHERE ticket_id ='$ticket_id'";
		Database::query($sql);		
	}
	public static function close_old_tickets(){
		$table_support_tickets = Database::get_main_table(TABLE_SUPPORT_TICKET);
		$now = api_get_utc_datetime();
		$sql = "UPDATE ".$table_support_tickets." SET status_id = 'CLS' , sys_lastedit_user_id ='".api_get_user_id()."' ,sys_lastedit_datetime ='".$now."',end_date ='".$now."' WHERE DATEDIFF('$now',sys_lastedit_datetime) > 7 AND status_id != 'CLS' AND status_id != 'NAT' AND status_id != 'REE'";
		Database::query($sql);		
	}
	public static function get_assign_log($ticket_id){
		$table_support_assigned_log = Database::get_main_table(TABLE_SUPPORT_ASSIGNED_LOG);
		$sql = "SELECT log.* FROM ".TABLE_SUPPORT_ASSIGNED_LOG." log  WHERE log.ticket_id = '$ticket_id' ORDER BY log.assigned_date";
		$result = Database::query($sql);
		$history = array();
		while ($row = Database::fetch_assoc($result)){
			if ($row['user_id'] != 0) $assignuser = api_get_user_info($row['user_id']);
			$insertuser = api_get_user_info($row['sys_insert_user_id']);
			$row['assigned_date'] = api_convert_and_format_date(api_get_local_time($row['assigned_date']), '%d/%m/%y-%H:%M:%S',_api_get_timezone());
			$row['assignuser']= ($row['user_id'] != 0)?('<a href="'.api_get_path(WEB_PATH).'main/admin/user_information.php?user_id='.$row['user_id'].'"  target="_blank">'.$assignuser['username'].'</a>') : get_lang('Unassign');
			$row['insertuser']= '<a href="'.api_get_path(WEB_PATH).'main/admin/user_information.php?user_id='.$row['sys_insert_user_id'].'"  target="_blank">'.$insertuser['username'].'</a>';
			$history[]=$row;
		}
		return $history;
	}
	public static function export_tickets_by_user_id($from, $number_of_items, $column, $direction ,$user_id=null){
		$table_support_category = Database::get_main_table(TABLE_SUPPORT_CATEGORY);
		$table_support_tickets = Database::get_main_table(TABLE_SUPPORT_TICKET);
		$table_support_priority = Database::get_main_table(TABLE_SUPPORT_PRIORITY);
		$table_support_status = Database::get_main_table(TABLE_SUPPORT_STATUS);
		$table_support_messages = Database::get_main_table(TABLE_SUPPORT_MESSAGE);
		$table_main_user = Database::get_main_table(TABLE_MAIN_USER);
		if(is_null($direction)) $direction ="DESC";
		if(is_null($user_id) || $user_id==0){
			$user_id = api_get_user_id();
		}

		$sql = "SELECT ticket.ticket_code, ticket.sys_insert_datetime , ticket.sys_lastedit_datetime , cat.name as category , CONCAT(user.lastname,' ', user.firstname) AS fullname , status.name as status , ticket.total_messages as messages , ticket.assigned_last_user as responsable
				FROM ".$table_support_tickets." ticket ,".$table_support_category." cat , ".$table_support_priority ." priority, ".$table_support_status ." status , ".Database::get_main_table(TABLE_MAIN_USER)." user
				WHERE cat.category_id = ticket.category_id AND ticket.priority_id = priority.priority_id AND ticket.status_id = status.status_id  AND user.user_id = ticket.request_user ";
		//Search simple
		if(isset($_GET['submit_simple'])){
			if($_GET['keyword']!=''){
				$keyword = Database::escape_string(trim($_GET['keyword']));
				$sql.=" AND (ticket.ticket_code = '".$keyword."' OR user.firstname LIKE '%".$keyword."%' OR user.lastname LIKE '%".$keyword."%'  OR concat(user.firstname,' ',user.lastname) LIKE '%".$keyword."%'  OR concat(user.lastname,' ',user.firstname) LIKE '%".$keyword."%' OR user.username LIKE '%".$keyword."%')  ";
			}
		}
		//Search advanced
		if(isset($_GET['submit_advanced'])){
			$keyword_category = Database::escape_string(trim($_GET['keyword_category']));
			$keyword_request_user = Database::escape_string(trim($_GET['keyword_request_user']));
			$keyword_admin = Database::escape_string(trim($_GET['keyword_admin']));
			$keyword_start_date_start = Database::escape_string(trim($_GET['keyword_start_date_start']));
			$keyword_start_date_end = Database::escape_string(trim($_GET['keyword_start_date_end']));
			$keyword_status = Database::escape_string(trim($_GET['keyword_status']));
			$keyword_source = Database::escape_string(trim($_GET['keyword_source']));
			$keyword_priority = Database::escape_string(trim($_GET['keyword_priority']));
			$keyword_range = Database::escape_string(trim($_GET['keyword_dates']));
			$keyword_unread = Database::escape_string(trim($_GET['keyword_unread']));
			$keyword_course = Database::escape_string(trim($_GET['keyword_course']));

			if($keyword_category !=''){
				$sql.=" AND ticket.category_id = '$keyword_category'  ";
			}
			if($keyword_request_user !=''){
			$sql.=" AND (ticket.request_user = '$keyword_request_user' OR user.firstname LIKE '%".$keyword_request_user."%' OR user.official_code LIKE '%".$keyword_request_user."%' OR user.lastname LIKE '%".$keyword_request_user."%'  OR concat(user.firstname,' ',user.lastname) LIKE '%".$keyword_request_user."%'  OR concat(user.lastname,' ',user.firstname) LIKE '%".$keyword_request_user."%' OR user.username LIKE '%".$keyword_request_user."%') ";
			}
			if($keyword_admin !=''){
			$sql.=" AND ticket.assigned_last_user = '$keyword_admin'  ";
			}
			if($keyword_status !=''){
			$sql.=" AND ticket.status_id = '$keyword_status'  ";
			}
			if($keyword_range == '' && $keyword_start_date_start!=''){
			$sql.= " AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') = '$keyword_start_date_start' ";
			}
			if ($keyword_range == '1' && $keyword_start_date_start!='' && $keyword_start_date_end !=''){
			$sql.= " AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') >= '$keyword_start_date_start' AND DATE_FORMAT( ticket.start_date,'%d/%m/%Y') <= '$keyword_start_date_end'";
			}
					if ($keyword_priority != ''){
			$sql.=" AND ticket.priority_id = '$keyword_priority'  ";
			}
			if($keyword_source != ''){
			$sql.= " AND ticket.source = '$keyword_source' ";
			}
			if($keyword_priority != ''){
			$sql.= " AND ticket.priority_id = '$keyword_priority' ";
			}
			if ($keyword_course != ''){
				$course_table = Database :: get_main_table(TABLE_MAIN_COURSE);
				$sql .= " AND ticket.course_id IN ( ";
				$sql .= "SELECT id FROM $course_table WHERE (title LIKE '%".$keyword_course."%' OR code LIKE '%".$keyword_course."%' OR visual_code LIKE '%".$keyword_course."%' )) ";
			}
			if($keyword_unread == 'yes'){
			$sql.= " AND ticket.ticket_id IN (SELECT ticket.ticket_id FROM  $table_support_tickets ticket,  $table_support_messages message,  $table_main_user user WHERE ticket.ticket_id = message.ticket_id   AND message.status = 'NOL'   AND message.sys_insert_user_id = user.user_id   AND user.status != 1   AND ticket.status_id != 'REE'   GROUP BY ticket.ticket_id)";
			}else if($keyword_unread == 'no'){
			$sql.= " AND ticket.ticket_id NOT IN (SELECT ticket.ticket_id FROM  $table_support_tickets ticket,  $table_support_messages message,  $table_main_user user WHERE ticket.ticket_id = message.ticket_id   AND message.status = 'NOL'   AND message.sys_insert_user_id = user.user_id   AND user.status != 1   AND ticket.status_id != 'REE'   GROUP BY ticket.ticket_id)";
			}	

		}


		//$sql .= " ORDER BY col$column $direction";
		$sql .= " LIMIT $from,$number_of_items";

		$result = Database::query($sql);
		$tickets[0] =  array(utf8_decode('Ticket#'), utf8_decode('Fecha'), utf8_decode('Fecha Edicion'), utf8_decode('Categoria'), utf8_decode('Usuario'), utf8_decode('Estado'), utf8_decode('Mensajes'), utf8_decode('Responsable') , utf8_decode('Programa') ) ;

		while ($row = Database::fetch_assoc($result)){
			if( $row['responsable'] != 0){
				$row['responsable']= api_get_user_info($row['responsable']);
				$row['responsable']= $row['responsable']['firstname'].' '.$row['responsable']['lastname'];
			}
			$row['sys_insert_datetime'] = api_format_date($row['sys_insert_datetime'],'%d/%m/%y - %I:%M:%S %p');
			$row['sys_lastedit_datetime'] = api_format_date($row['sys_lastedit_datetime'],'%d/%m/%y - %I:%M:%S %p');
			$row['category'] = utf8_decode($row['category']);
			$row['programa'] = utf8_decode($row['fullname']);
			$row['fullname'] = utf8_decode($row['fullname']);
			$row['responsable'] = utf8_decode($row['responsable']);
			$tickets[] = $row;
		}
		return $tickets;
	}
}
?>