<?php
/*
Plugin Name: SNFY - Scraped News From YCombinator.com
Description: List of latest news from YCombinator.
Version: 1.0
*/



add_filter('the_content', 'scrape_news', 1);
add_filter('widget_text', 'scrape_news', 1);

add_action('admin_menu', 'snfy_menu');
add_action('init', 'snfy_stylesheet');
add_action('init', 'jquery_scrollbar');

function snfy_menu() {
	add_options_page('SNFY', 'SNFY Settings', 'administrator', __FILE__, 'snfy_settings_page',plugins_url('/images/icon.png', __FILE__));
	
	add_action('admin_init', 'snfy_init');
}

function snfy_init(){
	$def_no = 5;
	$def_mins = 10;
	$def_title = 'Latest News';
	$last_scraped = time()- (12*60);
	$def_plugin_width = '220';
	$def_plugin_height = '350';
	
	add_option('news_no', $def_no);
	add_option('snfy_mins', $def_mins);
	add_option('snfy_title', $def_title);
	add_option('last_scraped', $last_scraped);
	add_option('scraped_news', maybe_serialize(array()));
	add_option('snfy_width', $def_plugin_width);
	add_option('snfy_height', $def_plugin_height);
}

function snfy_settings_page(){
	$result = check_actions();
	?>
	<div class="snfy_wrapper">
	<h2>SNFY Settings</h2>
	<p id="snfy_news">This is the settings section for <strong>SNFY</strong>. <br/>Feel free to change the number of news to be displayed and the
	minutes on when the next scraping would occur.
	On your page, please add the text [SNFY_NEWS] and it will automatically be replaced with
	the news if and only if the plugin is active too.</p>
	<?php
		if($result == 1){
			echo "<p class='snfy_success'>Settings successfully saved.</p>";
		}
	?>
	<form action="" method="post">
	<input type="hidden" name="snfy_settings_saved" value="1" />
	<table id="snfy_table">
		<tr>
			<td>
				Number of news to be displayed<br/>
			</td>
			<td>
				<select name="news_no">
					<?php
						$x = 5;
						$curr_val = get_option('news_no');
						for($x = 5; $x <= 30; $x+=5){
							if($curr_val == $x)
								echo "<option value='".$x."' selected>".$x."</option>";
							else
								echo "<option value='".$x."'>".$x."</option>";
						}
					?>
				</select>
			</td>
		</tr>
		<tr>
			<td>
				Number of minutes to scraped new news
			</td>
			<td><input type="text" name="snfy_mins" value="<?php echo get_option('snfy_mins'); ?>" size="3" maxlength="3"/></td>
		</tr>
		<tr>
			<td>News section Width</td>
			<td><input type="text" name="snfy_width" value="<?php echo get_option('snfy_width'); ?>" size="4" maxlength="4"/>&nbsp;<em>px</em></td>
		</tr>
		<tr>
			<td>News section Height</td>
			<td><input type="text" name="snfy_height" value="<?php echo get_option('snfy_height'); ?>" size="4" maxlength="4" />&nbsp;<em>px</em></td>
		</tr>
		<tr>
			<td>Your news title</td>
			<td><input type="text" name="snfy_title" value="<?php echo get_option('snfy_title'); ?>" size="40" /></td>
		</tr>
		<tr>
			<td colspan="2" align="right"><p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p></td>
		</tr>
	</table>
	</form>
	</div>
	<?php
}

function check_actions(){
	if($_POST['snfy_settings_saved'] == 1){
		$no = $_POST['news_no'];
		$mins = trim($_POST['snfy_mins']);
		$width = trim($_POST['snfy_width']);
		$height = trim($_POST['snfy_height']);
		$title = trim(strip_tags($_POST['snfy_title']));

		/*
		if(preg_match('/^[0-9]+$/', $mins)){
			$mins = $mins;
		}else{
			$mins = get_option('snfy_mins');
		} */
		if(!preg_match('/^[0-9]+$/', $mins))
			$mins = get_option('snfy_mins');
		if(!preg_match('/^[0-9]+$/', $width))
			$width = get_option('snfy_width');
		if(!preg_match('/^[0-9]+$/', $height))
			$height = get_option('snfy_height');
			
		$title = $title == "" ? 'Latest News' : $title;
		
		update_option('news_no', $no);
		update_option('snfy_mins', $mins);
		update_option('snfy_width', $width);
		update_option('snfy_height', $height);
		update_option('snfy_title', $title);
		
		return 1;
	}else{
		return 0;
	}
	
}
function scrape_news($content=''){
	$curr_time = time();
	$last_time = get_option('last_scraped');
	$sec = 60;
	$int = get_option('snfy_mins');
	
	if(($curr_time - $last_time)/$sec >= $int){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, MYCURL_AGENT);
		curl_setopt($ch, CURLOPT_FAILONERROR, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);

		curl_setopt($ch, CURLOPT_URL, 'http://news.ycombinator.com/newest');
		$result = curl_exec($ch);
		$dom = new DOMDocument();
		if(!$result){
			echo "cURL Error: ".curl_error($ch);
			echo "on line: ".curl_errno($ch);
			exit();
		}else{
			@$dom->loadHTML($result);
			$xpath = new DOMXPath($dom);
			
			$dist = 3;
			$length = get_option('news_no');
			$news = array();
			$index = 0;

			for($j=1, $i = 1; $j <= $length; $i+=3, $j++){
				$attrib = $xpath->evaluate('//table/tr[3]/td/table/tr['.$i.']/td[3]/a');
				$domain = $xpath->evaluate('//table/tr[3]/td/table/tr['.$i.']/td[3]/span');
				$news[$index]['title'] = $attrib->item(0)->nodeValue;
				$news[$index]['url'] = $attrib->item(0)->getAttribute('href');
				
				preg_match('/([a-zA-Z0-9-_.])+/i',$domain->item(0)->nodeValue, $match);
				$news[$index]['domain'] = $match[0] == null ? '' : $match[0];
				$index++;
			}
			curl_close($ch);
			
			update_option('scraped_news', maybe_serialize($news));
			update_option('last_scraped', time());
		}
	}
	
	$news = maybe_unserialize(get_option('scraped_news'));
	$title = get_option('snfy_title');
	$i = 1;
	$news_html = '<div id="scrollbar">';
	$news_html.= '<div class="scrollbar"><div class="track"><div class="thumb"><div class="end"></div></div></div></div>';
	$news_html.= '<div class="viewport">';
	$news_html.= '<div class="overview">';
	$news_html.= '<p class="title">'.$title.'</p>';
	$news_html .= '<ul>';
	foreach($news as $key => $val){
		$news_html .= '<li>'.$i++.'.&nbsp;<a href="'.$val['url'].'" rel="nofollow" target="_blank">'.$val['title'].'</a>';
		$news_html .= "&nbsp;&nbsp;&nbsp;&nbsp;<em>".$val['domain']."</em></li>";
	}
	$news_html .= '</ul>';
	$news_html .= '</div></div></div>';

	$content = str_replace('[SNFY_NEWS]', $news_html, $content);
	
	return $content;
}

function snfy_stylesheet(){
	$myStyleUrl = WP_PLUGIN_URL . '/SNFY/css/style.css';
	$myStyleFile = WP_PLUGIN_DIR . '/SNFY/css/style.css';
	
	if(file_exists($myStyleFile)) {
		wp_register_style('SNFYStylesheet', $myStyleUrl);
		wp_enqueue_style('SNFYStylesheet');
	}
}

add_action('wp_head', 'scrollbar');

function scrollbar(){
$width = get_option('snfy_width');
$height = get_option('snfy_height');
?>
	<script type="text/javascript">
	$(document).ready(function(){
		$('#scrollbar').css('width', '<?php echo $width; ?>');
		$('#scrollbar .viewport').css('width', '<?php echo $width-20; ?>');
		$('#scrollbar .viewport').css('height', '<?php echo $height; ?>');
		$('#scrollbar').tinyscrollbar();
	});
	</script>
	<?php
}
function jquery_scrollbar(){
	if(!is_admin()){
		wp_deregister_script('jquery_script');
		wp_deregister_script('jquery_scrollbar');
		
		wp_register_script('jquery_script', WP_PLUGIN_URL."/SNFY/js/jquery-1.4.4.min.js");
		wp_register_script('jquery_scrollbar', WP_PLUGIN_URL."/SNFY/js/jquery.tinyscrollbar.min.js");
		
		wp_enqueue_script('jquery_script');
		wp_enqueue_script('jquery_scrollbar');
		
	}
} 
?>