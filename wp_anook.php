<?php
/*/
 * Plugin Name: WP Anook
 * Plugin URI: https://wordpress.org/plugins/wp-anook/
 * Description: Insert a badge of a user / nook into your post
 * Version: 1.0.6
 * Author: SurDaft Jack
 * Author URI: http://surdaft.me
 * License: GPL3
 * Copyright 2015  Jack Stupple  (email : jacktstupple@gmail.com)
 *
 * Tweet me something @surdaft
/*/

/* Anook fetch
 * $part 				= Game / nook / user so what part of the api you want to use.
 * $search 				= What you want to search the api for so you can get the user SurDaft. 
 * $params 				= Eg page number / return 0's and lastly
 * $json_encode 		= return as json or an associative array
 */
function anook_fetch($part, $search, $params, $json_encode=false, $debug = 0){
	$cache = 1; // cache the data - HIGHLY RECOMMENDED to leave this on. Or Chocy bear may be unhappy. :(
	$hours = 10; // time before new data is used instead of the cache where it is recached.

	$cache_ago = strtotime($hours.' hours ago');

	$url = 'http://www.anook.com/api'."/".$part."/".$search;
	$i = 0; // if the first parameter
	$search_name = $part.'/'.$search;
	$searching = false;
	// add url attributes
	if($params){
		// if you are searching get fresh data each time
		if(strpos($params,'search=')!==false)
			$searching = true;

		// differentiate search to profile data
		if(is_string($params) && $searching){
			if(is_array($params))
				$params = implode('&',$params);
			$url = $url.'?'.$params;
			$search_name = $search_name.'?'.$params;
		} else {
			foreach($params as $key => $param){
				if($i == 0) $prefix = '?attributes='; // add the url prefix
				else $prefix = ',';
				$i = 1;

				$url = $url.$prefix.$param;
				$search_name = $search_name.$prefix.$param;
			}
		}
	}
	$url = $url.'&empty=1'; // adds the empty so that 0's are returned

	// if to use the cache
	if($cache){
			
		// global the wordpress database
		global $wpdb;
		// get table name
		$table_name = $wpdb->prefix.'anook';

		// if($wpdb->get_var("SHOW TABLES LIKE '".$table_name."'") != $table_name)
		// 	anook_db_build();

		$in_table = (array)$wpdb->get_results("SELECT * FROM ".$table_name." WHERE `search` = '".urlencode($search_name)."' AND TIMESTAMP > ".$cache_ago." LIMIT 1")[0];

		if($in_table){
			$output = json_decode($in_table['output'],1);
			$output = $output['data'];

			$output['url'] = $url;
			$output['source'] = 'cache';
		} else {
			// else get fresh data
			$c = curl_init(preg_replace('/(\s)/','%20',$url));
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true); // hides pre-emptive output
			$raw_output = curl_exec($c);
			curl_close($c);
			$output = json_decode($raw_output,true);
			$output = $output['data'];

			// insert into database
			$wpdb->insert(
				$table_name,
				array(
					'search' => urlencode($search_name), // the search used to get the data
					'output' => $raw_output
				)
			);

			// for frontend show where source is from
			$output['url'] = $url;
			$output['source'] = 'fresh';
		}
	} else {
		// not using cache, fresh data
		$c = curl_init(preg_replace('/(\s)/','%20',$url));
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true); // hides pre-emptive output
		$c = json_decode(curl_exec($c),true);
		$output = $c;
		$output = $output['data'];
		$output['url'] = $url;
		$output['source'] = 'fresh'; // This is posted in the console to notify if the data shown is cached or fresh
	}

	if($json_encode)
		return json_encode($output);
	else
		return $output;
}

function anookGetFollowers($atts){
	$user = anook_fetch('user', $atts['username'], array('attributes'=>'followerCount'));
	$followerCount = $user[0];
	return $followerCount['followerCount'];
}

// widget
class anook_widget extends WP_Widget{
	// tell wordpress about me!
	function __construct(){
		parent::__construct('anook_widget','Anook Badge',array('description'=>'Display an anook badge in your sidebar'));

	}
	// this is what is shown
	public function widget($args,$instance){
		$title = apply_filters('widget_title',$instance['title']);
		echo $args['before_widget'];
		if(!empty($title))
			echo $args['before_title'].$title.$args['after_title'];
		echo anook_show($instance);
		echo $args['after_widget'];
	}
	// backend options stuff
	public function form($instance){
		$options = array('user','nook','game');
		?>
		<div class="anook_admin">
			<p class="anook_admin_widget_title">
				<label for="<?php echo $this->get_field_id('title'); ?>">Title:</label>
				<input class="widefat" type="text" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php if(isset($instance['title'])) echo $instance['title']; ?>"  autocomplete='off' placeholder='If left blank no title will be shown :)' />
			</p>
			<p class='anook_admin_parts_list'>
				<label for="<?php echo $this->get_field_id('part'); ?>">Part:</label>
				<select name="<?php echo $this->get_field_name('part');?>" id="<?php echo $this->get_field_id('part');?>" autocomplete='off'>
					<?php foreach($options as $option){
						echo '<option value="'.$option.'" id="'.$option.'" '.(($instance['part']==$option)?'selected':'').'>'.ucfirst($option).'</option>';
					}?>
				</select>
			</p>
			<p class='anook_admin_search'>
				<label for="<?php echo $this->get_field_id('search'); ?>">Search:</label>
				<input type="text" id="<?php echo $this->get_field_id('search'); ?>" name="<?php echo $this->get_field_name('search'); ?>" value="<?php if(isset($instance['search'])) echo $instance['search']; ?>" class="widefat" autocomplete='off'>
			</p>
			<p class='anook_admin_show_games' <?php if($instance['part']!='user') echo 'style="display:none;"'; ?>>
				<input type='radio' name="<?php echo $this->get_field_name('show_games'); ?>" value='1' id='show-games' <?php if($instance['show_games'] == 1) echo 'checked'; ?> autocomplete='off'> <label for='show-games'>Show games</label><br />
				<input type='radio' name="<?php echo $this->get_field_name('show_games'); ?>" value='0' id='hide-games' <?php if($instance['show_games'] == 0) echo 'checked'; ?> autocomplete='off'> <label for='hide-games'>Hide games</label>
			</p>
			<div class='anook_admin_games_list' data-name="<?php echo $this->get_field_name('games_list'); ?>" >
				<p>
					<input type='text' placeholder='Search game' autocomplete='off' class="widefat"/><i class="fa fa-search"></i>
				</p>
				<p>Results:</p>
				<div class="results">
					None	
				</div>
				<p>Saved Games:</p>
				<div class="saved">
					<?php if(isset($instance['games_list'])&&is_array($instance['games_list'])){
						$temp_id = 0;
						foreach($instance['games_list'] as $k => $game){
							$game_id = $k;
							$temp_id++;
							echo '<div class="saved_game" data-id="'.$temp_id.'" data-game-id="'.$game_id.'"><span class="remove_game">-</span><span class="game_name">'.$game.'</span></div>';
							// hidden object for id
							echo "<input type='hidden' value='".$game."' name='".$this->get_field_name('games_list')."[".$game_id."]' data-game-id='".$game_id."' />";
						}
					}?>
				</div>
				<br />
			</div>
		</div>
	<?php }
	// save the options!
	public function update($new_instance, $old_instance){
		$instance = array();
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['part'] = strip_tags($new_instance['part']);
		$instance['search'] = strip_tags($new_instance['search']);
		$instance['show_games'] = $new_instance['show_games'];
		$instance['games_list'] = $new_instance['games_list'];
		return $instance;
	}
}

// build
function anook_show($atts){
	$html = '';
	// define attributes (params) to make bandwidth less an issue and specify data return
	if($atts['part'] == 'user'){
		$params = array('picture','country','followerCount','url','username');
	} elseif($atts['part'] == 'game' || $atts['part'] == 'nook'){
		$params = array('thumbnail','name','userCount','url');
	}
	$return = anook_fetch($atts['part'],$atts['search'],$params);
	$html .= '<script>console.log("Anook: '.$return['source'].'");</script>';
	$return = $return[0];

	if($atts['part'] == 'user')
		$html .= '<div class="anook-badge anook-user" id="anook-user-badge">
					<span class="logo-container"><a href="http://anook.com/" title="Anook" target="_blank"><img class="logo" src="'.plugin_dir_url(__FILE__).'images/anook_logo_dark.png" alt="Anook Logo"></a></span>
					<div class="img-container"><a href="'.$return['url'].'" target="_blank"><img src="http://anook.com/'.$return['picture'].'" alt="'.$return['username'].'\'s profile picture" /></a></div>
					<div class="right">
						<span id="username"><a href="'.$return['url'].'" target="_blank">'.$return['username'].'</a></span>
						<small id="country">'.$return['country'].'</small>
						<small>Followers: '.$return['followerCount'].'</small>
						<a id="follow-button" href="'.$return['url'].'" target="_blank">follow user</a>
					</div>';

	elseif($atts['part'] == 'nook' || $atts['part'] == 'game')
		$html .= '<div class="anook-badge anook-'.$atts['part'].'" id="anook-'.$atts['part'].'-badge">
					<a href="http://anook.com" title="Anook" target="_blank"><img class="logo" src="'.plugin_dir_url(__FILE__).'images/anook_icon_dark.png"></a>
					<div class="img-container-nook"><a href="'.$return['url'].'" target="_blank"><img src="http://anook.com/'.$return['thumbnail'].'" /></a></div>
					<div class="right">
						<span id="username"><a href="'.$return['url'].'" target="_blank">'.$return['name'].'</a></span>
						<small id="country-empty"> </small>
						<small>Followers: '.$return['userCount'].'</small>
						<a id="follow-button" href="'.$return['url'].'" target="_blank">follow '.$atts['part'].'</a>
					</div>';
	// if it has show games
	if(isset($atts['games_list']) && $atts['show_games']){
		$html .= '<ul class="games_list">';
		foreach($atts['games_list'] as $game){
			$game_data = anook_fetch('game','','search='.$game.'&attributes=id,name,picture,url',false);
			$user_data = anook_fetch('user',$return['username'].'/games','search='.$game.'&attributes=user',false, 0, 1);

			$html .= '<li class="game"><div class="img-container"><a href="'.$game_data[0]['url'].'" title="'.$game_data[0]['name'].'" target="_blank"><img src="http://anook.com/'.$game_data[0]['picture'].'"></a></div><div class="right"><a href="'.$game_data[0]['url'].'" title="'.$game_data[0]['name'].'" target="_blank">'.$game_data[0]['name'].'</a><small>Fame: '.(($user_data[0]['user']['fame']!="")?$user_data[0]['user']['fame']:0).'</small></div></li>';
		}
		$html .= '</ul>';
	}
	// end of anook-badge container
	$html .='</div>';
	// return the end html result, all the built bits of code
	return $html;
}

function anook_show_shortcode($atts){
	$html = '';
	foreach($atts as $key => $attr){
		if($key=='title') continue;
		// define attributes (params) to make bandwidth less an issue and specify data return
		if($key == 'user'){
			$params = array('picture','country','followerCount','url','username');
		} elseif($key == 'game' || $key == 'nook'){
			$params = array('thumbnail','name','userCount','url');
		}
		$return = anook_fetch($key,$attr,$params);
		$return = $return[0];
		$html .= '<script>console.log("'.$return['source'].'");</script>';
		if($key == 'user')
			$html .= '<div class="anook-badge anook-user" id="anook-user-badge">
						<span class="logo-container"><a href="http://anook.com" title="Anook" target="_blank"><img class="logo" src="'.plugin_dir_url(__FILE__).'images/anook_logo_dark.png"></a></span>
						<div class="img-container"><a href="'.$return['url'].'" target="_blank"><img src="http://anook.com/'.$return['picture'].'" /></a></div>
						<div class="right">
							<span id="username"><a href="'.$return['url'].'" target="_blank">'.$return['username'].'</a></span>
							<span id="country">'.$return['country'].'</span>
							<span id="followers">Followers: '.$return['followerCount'].'</span>
							<a id="follow-button" href="'.$return['url'].'" target="_blank">follow user</a>
						</div>
					</div>';
		elseif($key == 'nook' || $key == 'game')
			$html .= '<div class="anook-badge anook-'.$key.'" id="anook-'.$key.'-badge">
						<a href="http://anook.com" title="Anook" target="_blank"><img class="logo" src="'.plugin_dir_url(__FILE__).'images/anook_icon_dark.png"></a>
						<div class="img-container-nook"><a href="'.$return['url'].'" target="_blank"><img src="http://anook.com/'.$return['thumbnail'].'" /></a></div>
						<div class="right">
							<span id="username"><a href="'.$return['url'].'" target="_blank">'.$return['name'].'</a></span>
							<span id="country-empty"> </span>
							<span id="followers">Followers: '.$return['userCount'].'</span>
							<a id="follow-button" href="'.$return['url'].'" target="_blank">follow '.$key.'</a>
						</div>
					</div>';
	}
	// return the end html result, all the built bits of code
	return $html;
}

function anook_ajax(){
    print_r(anook_fetch($_POST['part'],$_POST['search'],$_POST['attr'],$_POST['json_encode']));
    exit();
}

// load js file to widgets area
function anook_admin_enqueue($hook) {
    if ( 'widgets.php' != $hook ) {
        return;
    }
    anook_enqueue($hook);
}

function anook_enqueue($hook) {
	wp_enqueue_style('font-awesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css');
	wp_enqueue_style('wp_anook', plugins_url('style.css',__FILE__));
	wp_enqueue_script('wp_anook', plugins_url('wp_anook.min.js',__FILE__), array('jquery'));
}

function anook_db_build(){
	global $wpdb;

	$table_name = $wpdb->prefix.'anook';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "
	CREATE TABLE ".$table_name." (
  		id int(11) NOT NULL AUTO_INCREMENT,
  		timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  		search varchar(255) DEFAULT '' NOT NULL,
  		output varchar(255) DEFAULT '' NOT NULL,
  		UNIQUE  KEY (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta($sql);
}
register_activation_hook( __FILE__, 'anook_db_build');

// init widget
function anook_widget_load(){
	register_widget('anook_widget');
}

add_shortcode('anook','anook_show_shortcode');
add_shortcode('anookFollowers','anookGetFollowers');

add_action('widgets_init', 'anook_widget_load'); // init widget
add_action( 'admin_enqueue_scripts', 'anook_admin_enqueue' ); // enqueue for admin
add_action( 'wp_enqueue_scripts', 'anook_enqueue' ); // enqueue for all
add_action( 'wp_ajax_anook_ajax', 'anook_ajax' ); // admin ajax
?>
