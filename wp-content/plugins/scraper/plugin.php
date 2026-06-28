<?php
require_once('libraries/db.php');

    if (!class_exists('SCRAPER_Plugin')) {

        class SCRAPER_Plugin{

            public static $plugin_file = '';
            
            public $plugin_name;
            public $plugin_basename;
            public $plugin_path;
            public $plugin_url;

            private $global_count = 0;
            private $index_count = 0;
            private $options;
            private $service_url;
            private $active_tab = 'schedule';
            private $intervals = array(
                '0'  => 'Not defined',
                '1'  => 'Every minute',
                '2'  => 'Every 5 minutes',
                '3'  => 'Every 10 minutes',
                '4'  => 'Every 15 minutes',
                '5'  => 'Every 30 minutes',
                '6'  => 'Every hour',
                '7'  => 'Every 3 hours',
                '8'  => 'Every 6 hours',
                '9'  => 'Every 12 hours',
                '10' => 'Every day',
                '11' => 'Every 2 days',
                '12' => 'Every 3 days',
                '13' => 'Weekly',
                '14' => 'Every 2 weeks',
                '15' => 'Monthly',
                '16' => 'Every 2 minutes',
            );

            private $delays = array(
                '0'  => 'Not defined',
                '1'  => '1 seconds',
                '2'  => '5 seconds',
                '3'  => '30 seconds',
                '4'  => '1 minute',
                '5'  => '5 minute',
                '6'  => '10 minute',
                '7'  => '10 seconds',
            );

            private $seconds = array();
            private $delay_seconds = array();
            private $last_http_status_code = 0;

            private $process_count = 0;

            public $get_last_file_id = -1;
            public $service_log;
            public $insert_error = array();

            /**
             * Construct the plugin object
             */
            public function __construct(){
                //define seconds
                $this->seconds['0']  = 0;
                $this->seconds['1']  = 60;
                $this->seconds['2']  = 60 * 5;
                $this->seconds['3']  = 60 * 10;
                $this->seconds['4']  = 60 * 15;
                $this->seconds['5']  = 60 * 30;
                $this->seconds['6']  = 60 * 60;
                $this->seconds['7']  = 60 * 60 * 3;
                $this->seconds['8']  = 60 * 60 * 6;
                $this->seconds['9']  = 60 * 60 * 12;
                $this->seconds['10'] = 60 * 60 * 24;
                $this->seconds['11'] = 60 * 60 * 24 * 2;
                $this->seconds['12'] = 60 * 60 * 24 * 3;
                $this->seconds['13'] = 60 * 60 * 24 * 7;
                $this->seconds['14'] = 60 * 60 * 24 * 14;
                $this->seconds['15'] = 60 * 60 * 24 * 3;
                $this->seconds['16'] = 60 * 2;

                $this->delay_seconds['0'] = 0;
                $this->delay_seconds['1'] = 1;
                $this->delay_seconds['2'] = 5;
                $this->delay_seconds['3'] = 30;
                $this->delay_seconds['4'] = 60;
                $this->delay_seconds['5'] = 300;
                $this->delay_seconds['6'] = 600;
                $this->delay_seconds['7'] = 10;

                // Get options
                $this->options     = get_option('SCRAPER');
                
                if(file_exists( plugin_dir_path( __FILE__ ) . '.dev' ))
                    $this->core_url    = file_get_contents(plugin_dir_path( __FILE__ ) . '.dev');
                else
                    $this->core_url    = 'https://scraper.site/visual-editor/';


                $this->frame_url   = trim($this->core_url);
                $this->service_url = $this->frame_url . 'service/';

                // Register Settings
                self::$plugin_file = SCRAPER_PLUGIN_MAIN_FILE_PATH;
                $this->plugin_name = strtolower(plugin_basename(dirname(self::$plugin_file)));
                $this->plugin_basename = plugin_basename(self::$plugin_file);
                $this->plugin_path = plugin_dir_path(self::$plugin_file);
                $this->plugin_url = plugin_dir_url(self::$plugin_file);

                if(isset($_GET['page'])){
                    $this->active_tab = $_GET['page'];
                }

                add_filter ( 'cron_schedules', array($this, 'spinner_once_a_minute') );
                add_action ( 'admin_menu', array($this, 'plugin_menu_pages') );

                if (! wp_next_scheduled ( 'wp_scraper_spin_hook' )) {
                    wp_schedule_event ( time (), 'once_a_minute', 'wp_scraper_spin_hook' );
                }

                add_action ( 'wp_scraper_spin_hook', array($this, 'wp_scraper_spin_wrap') );
                add_action ( 'admin_enqueue_scripts', array($this, 'wp_scraper_enqueue_scripts') );
                add_action ( 'wp_ajax_scraper_service', array($this, 'wp_scraper_service') );
                add_action ( 'wp_ajax_scraper_view_update', array($this, 'scraper_view_update') );

                //shortcode extension
                add_shortcode( 'scraper_shortcode', array($this, 'oembed_handler') );
            }

            //Cron
            public function spinner_once_a_minute($schedules) {
                $schedules ['once_a_minute'] = array (
                    'interval' => 60,
                    'display' => __ ( 'once a minute' ) 
                );
                
                return $schedules;
            }

            public function wp_scraper_spin_wrap(){
                //can be enabled for cron
                $this->process_queue();
            }

            public function process_queue() {
				$output = array();
				//this function can be used for custom processes.
				$tasks = $this->get_scheduled_tasks(true);
				foreach ($tasks['tasks'] as $key => $task) {
					$last_complete = (isset($task['last_complete']) && !empty($task['last_complete']) ? $task['last_complete'] : date('Y-m-d H:i:s'));
					$run_interval = (isset($task['run_interval']) ? $task['run_interval'] : 0);
					$currenttime = (isset($task['currenttime']) && !empty($task['currenttime']) ? $task['currenttime'] : date('Y-m-d H:i:s'));
					$interval_seconds = $this->seconds[$run_interval];
					if ($task['active'] == 1 && $interval_seconds > 0) {
						if (strtotime($currenttime) > (strtotime($last_complete) + $interval_seconds)) {
							$result = $this->process_task($task);
							$output[] = $result;
						}
					}
				}
				return $output;
			}

            public function get_task($hash=''){
                $output = array();
				if (!empty($hash)) {
					$tasks = $this->get_scheduled_tasks();
					foreach ($tasks['tasks'] as $key => $item) {
						if($item['hash'] == $hash){
							$output = $item;
						}
					}
				}
                return $output;
            }

            public function start_process($task){
				global $SCRAPER_DB;
				$SCRAPER_DB->add_items($task['hash']);
                $data = array(
                    'request'         => 'start_process',
                    'purchase_code'   => $this->get_purchase_code(),
                    'hash'            => $task['hash']
                );
                $output = $this->service($data);
            }

            public function finish_process($task, $result){
				global $SCRAPER_DB;
				$SCRAPER_DB->update_items($task['hash'], $result);
				$data = array(
                    'request'         => 'finish_process',
                    'purchase_code'   => $this->get_purchase_code(),
                    'hash'            => $task['hash'],
                    'result'          => json_encode($result)
                );
                $output = $this->service($data);
            }

            public function increase_page($task, $feedURL){
                $data = array(
                    'request'          => 'increase_page',
                    'purchase_code'    => $this->get_purchase_code(),
                    'hash'             => $task['hash'],
                    'current_page_url' => $feedURL
                );

                return $this->service($data);
            }

            public function increase_index($task){
                $data = array(
                    'request'          => 'increase_index',
                    'purchase_code'    => $this->get_purchase_code(),
                    'hash'             => $task['hash']
                );

                return $this->service($data);
            }

            public function reset_task($task){
                $data = array(
                    'request'          => 'reset_task',
                    'purchase_code'    => $this->get_purchase_code(),
                    'hash'             => $task['hash']
                );

                return $this->service($data);
            }

            public function reset_indexes($task){
                $data = array(
                    'request'          => 'reset_indexes',
                    'purchase_code'    => $this->get_purchase_code(),
                    'hash'             => $task['hash']
                );

                return $this->service($data);
            }

            public function check_excluded($task, $post, $exclude_field = 'post_title', $variables = array()){
                $output = false;

                if(isset($task['exclude_tags'])){
                    $exclude_tags = array_map(function($tag){
                        return strtolower(trim($tag));
                    }, explode(',', $task['exclude_tags']));

                    if($exclude_tags){
                        $exclude_field_parts = explode(':', $exclude_field);
                        $exclude_field_type  = @$exclude_field_parts[0];

                        if($exclude_field_type == 'variable'){
                            $exclude_field = @$exclude_field_parts[1];

                            if( isset($variables[$exclude_field]) ){
                                $output = $this->strposa(strtolower($variables[$exclude_field]), $exclude_tags) > -1 ? true : false;
                            }
                        }else{
                            $exclude_field = @$exclude_field_parts[0];

                            if( isset($post[$exclude_field]) ){
                                $output = $this->strposa(strtolower($post[$exclude_field]), $exclude_tags) > -1 ? true : false;
                            }
                        }
                    }
                }

                return $output;
            }

            public function check_conditions($task, $post, $variables = array(), $extra_string = ''){
                $output = true;
                
                if(isset($task['task_condition'])){
                    if($extra_string != ''){
                        preg_match('/(.*?)(\<|\>|\=)(.*)/', $extra_string, $matches);
                    }else{
                        preg_match('/(.*?)(\<|\>|\=)(.*)/', $task['task_condition'], $matches);
                    }                    
                    
                    if(isset($matches['3'])){
                        $matches[1] = trim($matches[1]);
                        $matches[2] = trim($matches[2]);
                        $matches[3] = trim($matches[3]);
                        
                        if($matches['2'] == '<'){
                            $query  = preg_replace('/\@(\w*)/', '$variables["$1"]', $matches['1']);                            
                            $result = eval('return '.$query.';');
                            $output = (float) $result < (float) $matches['3'];
                        }else if($matches['2'] == '>'){
                            $query  = preg_replace('/\@(\w*)/', '$variables["$1"]', $matches['1']);
                            $result = eval('return '.$query.';');                            
                            $output = (float) $result > (float) $matches['3'];
                        }else if($matches['2'] == '='){
                            if(strpos($matches['3'], '"') > -1){
                                $output = $variables[$matches['1']] == str_replace('"', '', $matches['3']);
                            }else{
                                $output = $variables[$matches['1']] == (float) $matches['3'];
                            }
                        }
                    }
                }

                return $output;
            }

            public function check_uniqueness($task, $post, $post_url){
                if(isset($task['uniqueness_method']) && @$task['uniqueness_method'] == 'URL'){
                    $uniqueness_hash = md5($post_url);
                }else{
                    if(isset($post['post_title'])){
                        $uniqueness_hash = md5($post['post_title']);
                    }else if(isset($post['post_content'])){
                        $uniqueness_hash = md5($post['post_content']);
                    }
                }

                $data = array(
                    'request'         => 'check_content',
                    'purchase_code'   => $this->get_purchase_code(),
                    'hash'            => $task['hash'],
                    'uniqueness_hash' => $uniqueness_hash
                );

                $output = $this->service($data);

                if($output['is_unique']){
                    return true;
                }else{
                    return false;
                }
            }

            public function create_uniqueness_hash($task, $post, $post_url){
                if(isset($task['uniqueness_method']) && @$task['uniqueness_method'] == 'URL'){
                    $uniqueness_hash = md5($post_url);
                }else{
                    if(isset($post['post_title'])){
                        $uniqueness_hash = md5($post['post_title']);
                    }else if(isset($post['post_content'])){
                        $uniqueness_hash = md5($post['post_content']);
                    }
                }

                $data = array(
                    'request'         => 'create_content',
                    'purchase_code'   => $this->get_purchase_code(),
                    'hash'            => $task['hash'],
                    'uniqueness_hash' => $uniqueness_hash
                );

                $output = $this->service($data);
            }

            public function check_translate(){
                $google_translate_token = $this->with_default('google_translate', false, $this->options);
                $yandex_translate_token = $this->with_default('yandex_translate', false, $this->options);
                $deepl_translate_token = $this->with_default('deepl_translate', false, $this->options);

                if($google_translate_token){
                    $request   = 'https://translation.googleapis.com/language/translate/v2';
                    $post_data = array(
                        'q'      => 'test message',
                        'target' => 'en',
                        'key'    => $google_translate_token
                    );

                    $result = wp_remote_post($request, array('body' => $post_data));
                    $result = json_decode(@$result['body'], true);

                    return $result;
                }else if($yandex_translate_token){
                    $request   = 'https://translate.yandex.net/api/v1.5/tr.json/translate';
                    $post_data = array(
                        'text'   => 'test message',
                        'lang'   => 'en',
                        'format' => 'html',
                        'key'    => $yandex_translate_token
                    );

                    $result = wp_remote_post($request, array('body' => $post_data));
                    $result = json_decode(@$result['body'], true);

                    return $result;
                }else if($deepl_translate_token){
                    $request   = 'https://api.deepl.com/v2/translate';
                    $post_data = array(
                        'text'        => 'test message',
                        'target_lang' => 'en',
                        'auth_key'    => $deepl_translate_token
                    );

                    $result = wp_remote_post($request, array('body' => $post_data));
                    $result = json_decode(@$result['body'], true);

                    return $result;
                }else{
                    return false;
                }
            }

            public function translate($content, $language){
                $output = $content;
                $google_translate_token = $this->with_default('google_translate', false, $this->options);
                $yandex_translate_token = $this->with_default('yandex_translate', false, $this->options);
                $deepl_translate_token  = $this->with_default('deepl_translate', false, $this->options);

                if($google_translate_token){
                    $request   = 'https://translation.googleapis.com/language/translate/v2';
                    $post_data = array(
                        'q'      => $output,
                        'target' => $language,
                        'key'    => $google_translate_token
                    );

                    $result = wp_remote_post($request, array('body' => $post_data));

                    if ( !is_wp_error($result) ) {
                        if($result['response']['code'] == 200){
                            $result = json_decode(@$result['body'], true);

                            if(@$result['data']['translations'][0]['translatedText']){
                                $output = @$result['data']['translations'][0]['translatedText'];
                            }
                        }
                    }
                }else if($yandex_translate_token){
                    $request   = 'https://translate.yandex.net/api/v1.5/tr.json/translate';
                    $post_data = array(
                        'text'   => $output,
                        'lang'   => $language,
                        'format' => 'html',
                        'key'    => $yandex_translate_token
                    );

                    $result = wp_remote_post($request, array('body' => $post_data));

                    if ( !is_wp_error($result) ) {
                        if($result['response']['code'] == 200){
                            $result = json_decode(@$result['body'], true);

                            if(@$result['text'][0]){
                                $output = @$result['text'][0];
                            }
                        }
                    }
                }else if($deepl_translate_token){
                    $request   = 'https://api.deepl.com/v2/translate';
                    $post_data = array(
                        'text'        => $output,
                        'target_lang' => $language,
                        'auth_key'    => $deepl_translate_token
                    );

                    $result = wp_remote_post($request, array('body' => $post_data));

                    if ( !is_wp_error($result) ) {
                        if($result['response']['code'] == 200){
                            $result = json_decode(@$result['body'], true);

                            if(@$result['translations'][0]){
                                $output = @$result['translations'][0]['text'];
                            }
                        }
                    }
                }

                return $output;
            }

            public function spin_content($content){
                $output = $content;
                $spinner_token = $this->with_default('spinner_code', false, $this->options);
                $domain        = get_site_url();

                if($spinner_token){
                    $request   = 'http://aispinner.org/service/?request=rewrite';
                    $post_data = array(
                        'domain'   => $domain,
                        'token'    => $spinner_token,
                        'level'    => 85,
                        'language' => 'en',
                        'content'  => $content
                    );

                    $result = wp_remote_post($request, array('body' => $post_data));

                    if ( !is_wp_error($result) ) {
                        if($result['response']['code'] == 200){
                            $result = json_decode(@$result['body'], true);

                            if(@$result['rewritten_content']){
                                $output = @$result['rewritten_content'];
                            }
                        }
                    }
                }

                return $output;
            }

            public function rel2abs($rel, $base) {
                if (empty($rel)) $rel = ".";
                if (parse_url($rel, PHP_URL_SCHEME) != "" || strpos($rel, "//") === 0) return $rel; //Return if already an absolute URL
                if ($rel[0] == "#" || $rel[0] == "?") return $base.$rel; //Queries and anchors
                extract(parse_url($base)); //Parse base URL and convert to local variables: $scheme, $host, $path
                $path = isset($path) ? preg_replace('#/[^/]*$#', "", $path) : "/"; //Remove non-directory element from path
                if ($rel[0] == '/') $path = ""; //Destroy path if relative url points to root
                $port = isset($port) && $port != 80 ? ":" . $port : "";
                $auth = "";
                if (isset($user)) {
                    $auth = $user;
                    if (isset($pass)) {
                        $auth .= ":" . $pass;
                    }
                    $auth .= "@";
                }
                $abs = "$auth$host$path$port/$rel"; //Dirty absolute URL
                for ($n = 1; $n > 0; $abs = preg_replace(array("#(/\.?/)#", "#/(?!\.\.)[^/]+/\.\./#"), "/", $abs, -1, $n)) {} //Replace '//' or '/./' or '/foo/../' with '/'
                return $scheme . "://" . $abs; //Absolute URL is ready.
            }

            public function replace_unescape($string){
                return preg_replace_callback('/\\\\u(\w{4})/', function ($matches) {
                    return html_entity_decode('&#x' . $matches[1] . ';', ENT_COMPAT, 'UTF-8');
                }, $string);
            }

            public function convertImages($output, $task, $post_title = '', $used_images = array()){
                preg_match_all('/<img(.*?)src=("|\'|)(.*?)("|\'| )(.*?)>/s', $output, $images);
                foreach ($images[3] as $key => $imageURL) {
                    if (empty($imageURL)) {
                        continue;
                    }
                    if(@$task['filename']){
                        $filename = $this->process_filename($task['filename'], $imageURL, $post_title);
                    }else{
                        $filename = false;
                    }
                    
                    if(isset($used_images[$imageURL]) && @$used_images[$imageURL]){
                        $replacedImage = $used_images[$imageURL];
                    }else{
                        $replacedImage = $this->upload_image($imageURL, -1, false, false, 'URL', @$filename);
                        $used_images[$imageURL] = $replacedImage;
                    }

                    $output = str_replace($imageURL, $replacedImage, $output);
                }

                return $output;
            }

            public function render_content($data, $content, $fields, $variables, $images, $gallery, $task_data, $gallery_field = array(), $post_url = ''){
                $output = $content;

                //static variable
                $output = str_replace('{{content}}', $data, $output);
                $output = str_replace('{{source_url}}', $post_url, $output);
                
                //Index changes
                if( strpos( $output, '{{index}}' )){
                    $output = str_replace('{{index}}', $this->index_count, $output);
                    $this->index_count++;
                }
                
                if($post_url){
                    $source_domain = parse_url($post_url);
                    $output = str_replace('{{source_domain}}',  $source_domain['host'], $output);
                }

                if($variables){
                    foreach ($variables as $key => $value) {
                        $output = str_replace('{{'.$key.'}}', $value, $output);
                    }
                }

                if($images){
                    foreach ($images as $key => $value) {
                        $output = str_replace('{{'.$key.'}}', $value, $output);
                    }
                }

                if($gallery){
                    $gallery_attributes = '';

                    if(isset($gallery_field)){
                        if(@$gallery_field['galleryColumns']){
                            $gallery_attributes.='columns="'.$gallery_field['galleryColumns'].'" ';
                        }

                        if(@$gallery_field['gallerySize']){
                            $gallery_attributes.='size="'.$gallery_field['gallerySize'].'" ';
                        }
                    }

                    $output = str_replace('{{gallery}}', '[gallery '.$gallery_attributes.' ids="' . implode(',', $gallery) . '"]', $output);
                }

                //clear all shortcodes
                $output = preg_replace('/\{\{(.*?)\}\}/', '', $output);

                return $output;
            }

            public function decode_bitly($url){
                if(isset($url)){
                    $decoded_url  = $url;

                    $bitly_login  = $this->with_default('bitly_login', false, $this->options);
                    $bitly_key    = $this->with_default('bitly_key', false, $this->options);

                    if($bitly_login && $bitly_key){
                        $bitly = new BitLy($bitly_login, $bitly_key);
                        $decoded_url = $bitly->expandUrlByUrl($url);
                    }

                    return $decoded_url;
                }else{
                    return $url;
                }
            }

            public function transform($content, $parameters, $fields, $variables = array(), $images = array(), $gallery = array(), $task_data = array(), $gallery_field = array(), $post_url = '', $task = array(), $post = array()){
                $escape_characters = true;

                $output = $content;
                $output = $this->render_content($output, $parameters['content'], $fields, $variables, $images, $gallery, $task_data, $gallery_field, $post_url);

                //apply find & replace
                if(isset($parameters['replaces'])){
                    foreach ($parameters['replaces'] as $key => $find_replace) {
                        //$output = str_replace($find_replace['find'], $find_replace['replace'], $output);
                        $output = preg_replace('/'.$find_replace['find'].'/', $find_replace['replace'], $output);
                    }
                }

                if($parameters['translate'] != ''){
                    $output = $this->translate($output, $parameters['translate']);
                }

                if(isset($parameters['decodeBitly']) && $parameters['decodeBitly'] == 'true'){
                    $output = $this->decode_bitly($output);
                }

                if(isset($parameters['spinner']) && $parameters['spinner'] == 'true'){
                    //$output = $this->spin_content($output);
                }

                if(isset($parameters['isNumber']) && $parameters['isNumber'] == 'true'){
                    if(isset($parameters['cleanNonNumerical']) &&  @$parameters['cleanNonNumerical'] == 'true'){
                        $output = preg_replace('/[^\d\.]/', '', $output);
                    }

                    $value = floatval($output);

                    if(isset($parameters['math'])){
                        $parameters['math'] = str_replace('value', '$value', $parameters['math']);
                        $output = eval('return '.$parameters['math'] . ';');
                    }
                }

                if(@$parameters['stripAds'] == 'true'){
                    $output = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $output);
                }

                if(@$task['download_images'] == '1'){
                    $output = $this->convertImages($output, $task, @$post['post_title'], array());
                }

                if($parameters['stripLinks'] == 'true'){
                    $output = preg_replace('#<a.*?>(.*?)</a>#i', '\1', $output);
                }

                if($parameters['stripTags'] == 'true'){
					if (is_array($output)) {
						$output = array_map( 'strip_tags', $output );
					} else {
						$output = strip_tags($output);
					}
				}

                if(@$parameters['clipEnd'] && (int)@$parameters['clipEnd'] > 0){
                    $output = substr($output, (int)@$parameters['clipStart'], (int)@$parameters['clipEnd']);
                }

                if(@$parameters['clipWordEnd'] && (int)@$parameters['clipWordEnd'] > 0){
                    $explodedContent = explode(' ', $output);

                    if(count($explodedContent) > 0){
                        $output = implode(' ', array_slice($explodedContent, (int)@$parameters['clipWordStart'], (int)@$parameters['clipWordEnd']));
                    }
                }

                if($parameters['attributeParse'] == 'background-image'){
					if (is_array($output)) {
						//preg_match('/url\((\'|\"|)(.*?)(\'|\"|)\)/', $output, $matches);
					} else {
						preg_match('/url\((\'|\"|)(.*?)(\'|\"|)\)/', $output, $matches);
						if(isset($matches[2])){
							$output = @$matches[2];
							$output = preg_replace('/\\\3a/i', ':', $output);
							$output = preg_replace('/\\\20/i', '', $output);
							$output = preg_replace('/\\\3d/i', '=', $output);
							$output = preg_replace('/\\\26/i', '&', $output);

							$output = str_replace(' ', '', $output);
						}
					}

                    $escape_characters = false;
                }

                if(@$parameters['splitContent'] == 'true' && $parameters['splitDelimiter']){
                    $output = explode($parameters['splitDelimiter'], $output);
                }

                //unescape characters
                if($escape_characters){
                    $output = $this->replace_unescape($output);
                }

                //clean scraper's proxy links
                $output = str_replace('https://scraper.site/visual-editor-beta/service/components/proxy.php/', '', $output);
                $output = str_replace('https://scraper.site/visual-editor/service/components/proxy.php/', '', $output);

                return $output;
            }

            public function date_transform($date_string){
                $date_stamp = strtotime($date_string);
                $post_date  = date("Y-m-d H:i:s", $date_stamp);

                return $post_date;
            }

            public function clear_url_params($url){
                $url = explode('?', $url);
                $url = explode('&', $url[0]);

                return trim($url[0]);
            }

            public function is_post_unique($unique_value, $uniqueness_type, $post_type, $task_data){
                $last_posts = $this->get_latest_posts($post_type, -1);
                $is_unique  = -1;

                foreach ($last_posts as $key => $post) {
                    $post_source_url = @$post['post_source_url'][0];

                    if(isset($task_data['connection']['ignore_params'])){
                        if($task_data['connection']['ignore_params'] == 'true' || $task_data['connection']['ignore_params'] == true){
                            $post_source_url = $this->clear_url_params($post_source_url);
                            $unique_value    = $this->clear_url_params($unique_value);
                        }
                    }

                    if(
                        $uniqueness_type == 'post_title' &&
                        trim($post['name']) == trim($unique_value)
                    ){
                        $is_unique = @$post['id'];
                    }else if(
                        $uniqueness_type == 'URL' &&
                        $post_source_url == $unique_value
                    ){
                        $is_unique = @$post['id'];
                    }else if(
                        $uniqueness_type == 'product_sku' &&
                        trim($post['product_sku'][0]) == trim($unique_value)
                    ){
                        $is_unique = @$post['id'];
                    }
                }

                return $is_unique;
            }

            public function process_filename($filename, $url, $post_title = ''){
                $output = $filename;

                $path_parts = pathinfo($url);

                if($path_parts['filename']){
                    $output = str_replace('{{originalname}}', $path_parts['filename'], $output);    
                }
                
                $output = str_replace('{{hash}}', md5(time()), $output);
                $output = str_replace('{{index}}', $this->global_count, $output);
                $output = str_replace('{{random}}', rand(100000, 900000), $output);
                $output = str_replace('{{post_title}}', sanitize_title($post_title), $output);

                $this->global_count++;

                return $output;
            }

            public function is_url($uri){
                if(preg_match( '/^(http|https):\\/\\/[a-z0-9_]+([\\-\\.]{1}[a-z_0-9]+)*\\.[_a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i' ,$uri)){
                  return $uri;
                }
                else{
                    return false;
                }
            }

			public function showText($content) {
				$content = strip_tags($content);
				$content = substr($content, 0, 255);
				return $content;
			}

			public function process_content($post_url, $data, $task, $manually_triggered = false){
                $task = $this->get_task($task['hash']);

                if($task['active'] == 0 && $manually_triggered == false){
                    return false;
                }

                if((int) $data['connection']['total_run'] > 0 && (int) $data['connection']['total_run'] <= (int) $task['count_run']){
                    return false;
                }

                if($task['post_update'] < 1){
                    $this->increase_index($task);
                }

                if(isset($task['task_limit']) && $task['task_limit'] > 0 && $task['task_limit'] <= $this->process_count){
                    return false;
                }

                //increase counter
                $this->process_count++;

                //post delay, if there is any
                if(@$task['run_delay'] && @$task['run_delay'] != '0'){
                    if($this->delay_seconds[$task['run_delay']]){
                        sleep($this->delay_seconds[$task['run_delay']]);
                    }
                }

                if (isset($data['urlType']) && $data['urlType'] == 'rss') {
					$post_html = $post_url;
					$post_url = $data['contentURL'];
				} else {
					$post_html = $this->get_url($post_url, $data, $data['feedURL']);
				}

                $post = array(
                    'post_status'   => $task['post_status'],
                    'post_type'     => $task['post_type'],
                    'post_category' => array()
                );

                if(@$task['category_id']){
                    $post['post_category'] = array($task['category_id']);
                }

                $featured_image = -1;
                $post_author    = 1;
                $post_slug      = -1;

                $images    = array();
                $gallery   = array();
                $gallery_field = array();

                $variables = array();
                $key_names = array();
                $success   = false;
                $query_success = 0;
                $categories     = array();
                $custom_fields  = array();
                $tag_taxonomies = array();
                $product_type = false;

                $used_images   = array();
                $attach_images = array();
                $field_by_type = array();
                $required_count = 0;
                $required_success = 0;

                $uniqueness  = -1;

                //query variables
                foreach ($data['fields'] as $key => $field) {
                    if(
                        $field['type'] == 'variable' || 
                        $field['type'] == 'shortcode' || 
                        $field['type'] == 'image' ||
                        $field['type'] == 'gallery'
                    ){
                        if($task['parse_method'] == 'xpath'){
                            $field_content = $this->parse_xpath($post_html, $field['path'], $field['prop']);

                            if($field_content){
                                $query_success++;
                            }
                        }else{
                            $field_content = $this->parse_regex($post_html, $field['path'], $field);

                            if($field_content){
                                $query_success++;
                            }
                        }

                        if($field['type'] == 'variable'){
                            $variables[$field['name']] = $this->transform(@$field_content[0], $field, $data['fields'], array(), array(), array(), $data, array(), $post_url);
                        }

                        if($field['type'] == 'shortcode'){
                            $variables[$field['name']] = $this->transform(@$field_content[0], $field, $data['fields'], array(), array(), array(), $data, array(), $post_url);
                        }
                    }
                }

                //get post title
                foreach ($data['fields'] as $key => $field) {
                    if($field['type'] == 'post_title'){
                        $field_content      = $this->parse_xpath($post_html, $field['path'], $field['prop']);
                        $post['post_title'] = $this->transform(@$field_content[0], $field, $data['fields'], $variables, $images, array(), $data, array(), $post_url);
                    }else if($field['type'] == '_sku'){
                        $field_content        = $this->parse_xpath($post_html, $field['path'], $field['prop']);
                        $post[$field['type']] = $this->transform(@$field_content[0], $field, $data['fields'], $variables, $images, array(), $data, array(), $post_url);
                    }
                }

                if($task['uniqueness_method'] == 'post_title' && $task['post_update'] == -1){
                    $uniqueness = $this->is_post_unique($post['post_title'], 'post_title', $task['post_type'], $data);
                }

                if($task['uniqueness_method'] == 'URL' && $task['post_update'] == -1){
                    $uniqueness = $this->is_post_unique($post_url, 'URL', $task['post_type'], $data);
                }

                //check post's uniquness
                if($task['uniqueness_method'] == 'product_sku' && $task['post_update'] == -1){
                    $uniqueness = $this->is_post_unique($post['_sku'], 'product_sku', $task['post_type'], $data);
                }

                if($uniqueness > -1 && @$task['track_changes'] == '0'){
                    return array(
                        'postTitle'      => isset($post['post_title']) ? $post['post_title'] : '',
                        'postId'         => -1,
                        'post'           => $post,
                        'featured_image' => false,
                        'images'         => false,
                        'timestamp'      => time(),
                        'date'           => date('Y-m-d H:i:s'),
                        'success'        => false,
                        'is_unique'      => false,
                        'excluded'       => false
                    );
                }

                //check excluded tags
                $excluded = $this->check_excluded($task, $post, @$task['exclude_field'], $variables);

                if($excluded){
                    return false;
                }
                
                if(strpos($task['task_condition'], 'AND') ){
                    $string_explode = explode('AND', $task['task_condition']);
                    $result_arr = array();
                    if($string_explode){
                        foreach ($string_explode as $string_e_value) {
                            $string_e_value = str_replace('(', '', $string_e_value);
                            $string_e_value = str_replace(')', '', $string_e_value);
                            $result_arr[] = $this->check_conditions($task, $post, $variables,  $string_e_value);
                        }
                    }
                    if(count(array_filter($result_arr)) == count($result_arr)) {
                        //do nothing
                    } else {
                        return false;
                    }
                } else if(strpos($task['task_condition'], 'OR') ){
                    $string_explode = explode('OR', $task['task_condition']);
                    $result_arr = array();
                    if($string_explode){
                        foreach ($string_explode as $string_e_value) {
                            $string_e_value = str_replace('(', '', $string_e_value);
                            $string_e_value = str_replace(')', '', $string_e_value);
                            $result_arr[] = $this->check_conditions($task, $post, $variables,  $string_e_value);
                        }
                    }
                    if(!array_filter($result_arr)) {
                        return false;
                    }
                } else {
                    $check_conditions = $this->check_conditions($task, $post, $variables);
                    
                    if(!$check_conditions){
                        return false;
                    }
                }


                foreach ($data['fields'] as $key => $field) {
                    if(
                        $field['type'] == 'variable' || 
                        $field['type'] == 'image' ||
                        $field['type'] == 'downloaded_file' ||
                        $field['type'] == 'gallery'
                    ){
                        if($task['parse_method'] == 'xpath'){
                            $field_content = $this->parse_xpath($post_html, $field['path'], $field['prop']);

                            if($field_content){
                                $query_success++;
                            }
                        }else{
                            $field_content = $this->parse_regex($post_html, $field['path'], $field);

                            if($field_content){
                                $query_success++;
                            }
                        }

                        if($field['type'] == 'gallery'){
							/**
							* Process image after Post Creation / Updation to reduce duplicate image downloading.
							*/
                            $gallery_field = $field;
                        }else if($field['type'] == 'image'){
							/**
							* Process image after Post Creation / Updation to reduce duplicate image downloading.
							*/
                        }else if($field['type'] == 'downloaded_file'){
                            $filename = false;

                            if(@$task['filename']){
                                $filename = $this->process_filename($task['filename'], @$field_content[0], @$post['post_title']);
                            }

                            $attachment_url = $this->transform(@$field_content[0], $field, $data['fields'], array(), array(), array(), $data, array(), $post_url, $task, $post);

                            $processed_image = $this->upload_image($attachment_url, -1, $field, $data['feedURL'], 'URLID', $filename);

                            $images[$field['name']] = @$processed_image[1];

                            if($images[$field['name']]){
                                $attach_images[] = @$processed_image[0];
                            }
                        }
                    }
                }

                //query fields
                foreach ($data['fields'] as $key => $field) {
                    if($task['parse_method'] == 'xpath'){
                        $field_content = $this->parse_xpath($post_html, $field['path'], $field['prop']);

                        if($field_content){
                            $query_success++;

                            if(isset($field['isRequired']) && @$field['isRequired'] == 'true'){
                                $required_success++;
                            }
                        }
                    }else{
                        $field_content = $this->parse_regex($post_html, $field['path'], $field);

                        if($field_content){
                            $query_success++;

                            if(isset($field['isRequired']) && @$field['isRequired'] == 'true'){
                                $required_success++;
                            }
                        }
                    }

                    if($field['type'] == 'featured_image' || $field['type'] == 'gallery' || $field['type'] == 'image'){
                        /**
						 * Process image after Post Creation / Updation to reduce duplicate image downloading.
						 */
                    }else if($field['type'] == 'variable'){
                        //Pass
                    }else if($field['type'] == 'post_title'){
                        $post[$field['type']] = $this->transform(@$field_content[0], $field, $data['fields'], $variables, $images, array(), $data, array(), $post_url, $task, $post);
                    }else if($field['type'] == 'post_date'){
                        //Format date
                        $date = $this->transform(@$field_content[0], $field, $data['fields'], $variables, $images, array(), $data, array(), $post_url, $task, $post);

                        $post[$field['type']]  = $this->date_transform($date);
                        $post['post_date_gmt'] = $this->date_transform($date);
                        $post['post_modified'] = $this->date_transform($date);
                        $post['post_modified_gmt'] = $this->date_transform($date);
                    }else if($field['type'] == 'post_content'){
                        $multiple_content_element = '';

                        foreach (@$field_content as $key => $value) {
                            $multiple_content_element.=$this->transform($value, $field, $data['fields'], $variables, $images, $gallery, $data, $gallery_field, $post_url, $task, $task, $post);
                        }

                        if(count($field_content) == 0){
                            $multiple_content_element = $this->transform('', $field, $data['fields'], $variables, $images, $gallery, $data, $gallery_field, $post_url, $task, $task, $post);
                        }

                        $post[$field['type']] = $multiple_content_element;
                    }else if($field['type'] == 'post_excerpt'){
                        $multiple_content_element = '';

                        foreach (@$field_content as $key => $value) {
                            $multiple_content_element.=$this->transform($value, $field, $data['fields'], $variables, $images, $gallery, $data, $gallery_field, $post_url, $task, $task, $post);
                        }

                        $post[$field['type']] = $multiple_content_element;
                    }else if($field['type'] == 'tags_input'){
                        $tags_input = array();

                        foreach ($field_content as $field_key => $field_value) {
                            $tags_input[] = $this->transform(@$field_value, $field, $data['fields'], $variables, $images, array(), $data, array(), $post_url);
                        }

                        if(!$field['name']){
                            $field['name'] = 'post_tag';
                        }

                        if(!@$tag_taxonomies[$field['name']]){
                            $tag_taxonomies[$field['name']] = array();
                        }

                        if($field['splitContent'] == 'true'){
                            $tag_taxonomies[$field['name']] = @$tags_input[0];
                        }else{
                            $tag_taxonomies[$field['name']] = @$tags_input;
                        }

                        $post['tags_input'] = $tag_taxonomies[$field['name']];
                    }else if($field['type'] == 'post_author'){
                        $post_author = $this->transform(@$field_content[0], $field, $data['fields'], $variables, $images, array(), $data, array(), $post_url, $task, $post);
                    }else if($field['type'] == 'post_slug'){
                        $post_slug = $this->transform(@$field_content[0], $field, $data['fields'], $variables, $images, array(), $data, array(), $post_url, $task, $post);
                    }else if($field['type'] == 'post_category'){
                        foreach ($field_content as $field_key => $field_value) {
                            $categories[] = $this->transform(@$field_value, $field, $data['fields'], $variables, $images, array(), $data, array(), $post_url);
                        }

                        if($field['splitContent'] == 'true'){
                            $post[$field['type']] = @$categories[0];
                        }else{
                            $post[$field['type']] = @$categories;
                        }
                    }else{
                        //Custom field
                        if(@$field['isMultiple'] == 'true'){
                            if(@$field_content){
                                foreach ($field_content as $key => $content) {
                                    if(!@$custom_fields[$field['type']]){
                                        $custom_fields[$field['type']] = array();
                                    }

                                    @$custom_fields[$field['type']][@$field['name']][] = $this->transform(@$content, $field, $data['fields'], $variables, $images, $gallery, $data, $gallery_field, $post_url);
                                }
                            }else{
                                $possible_split = $this->transform(@$field_content[0], $field, $data['fields'], $variables, $images, $gallery, $data, $gallery_field, $post_url);

                                foreach (@$possible_split as $key => $content) {
                                    @$custom_fields[$field['type']][@$field['name']][] = $content;
                                }
                            }
                        }else{
                            @$custom_fields[$field['type']] = $this->transform(@$field_content[0], $field, $data['fields'], $variables, $images, $gallery, $data, $gallery_field, $post_url);
                        }

                        $field_by_type[$field['type']] = $field;

                        if($field['type'] == '_product_url' || $field['type'] == '_button_text'){
                            $product_type = 'external';
                        }
                    }

                    if(isset($field['isRequired']) && @$field['isRequired'] == 'true'){
                        $required_count++;
                    }
                }

                if($query_success > 0 && $required_count == $required_success){
                    if($task['post_update'] == 0){
                        $post['post_title'] = 'Shortcodes Updated!';
                        $post['ID'] = '1';
                        $success = true;

                        update_option( 'SCRAPER_shortcode_variable_'.$task['hash'], $variables );
                    }else if($uniqueness > -1 && @$task['track_changes'] == '1'){
                        $post['ID'] = $uniqueness;

                        if(isset($data['other']) && @$data['other']){
                            if(@$data['other']['noStatusChange'] == '1'){
                                unset($post['post_status']);
                            }
                        }
                        $post['post_date_gmt'] = date('Y-m-d H:i:s');
                        $success = wp_update_post( $post );
                    }else if($task['post_update'] != -1){
                        $post['ID'] = $task['post_update'];
                        $post['post_date_gmt'] = date('Y-m-d H:i:s');
                        $success = wp_update_post( $post );
                    }else{
                        $post['ID'] = $success = wp_insert_post( $post, $this->insert_error);

                        if(isset($data['other']) && @$data['other']){
                            if(@$data['other']['postFormat'] && @$data['other']['postFormat'] != '0'){
                                set_post_format( $post['ID'] , $data['other']['postFormat']);
                            }
                        }
                    }

                    if(isset($post['ID'])){
                        update_post_meta( $post['ID'], '_scraper_post_source_url', $post_url );

                        if(isset($task['hash'])){
                            update_post_meta( $post['ID'], '_scraper_task_hash', @$task['hash'] );
                        }
                    }

					foreach ($data['fields'] as $key => $field) {
						if (
							$field['type'] == 'featured_image' ||
							$field['type'] == 'image' ||
							$field['type'] == 'gallery'
						) {
							if ($task['parse_method'] == 'xpath') {
								$field_content = $this->parse_xpath($post_html, $field['path'], $field['prop']);
							} else {
								$field_content = $this->parse_regex($post_html, $field['path'], $field);
							}
							if (!empty($field_content)) {
								if ($field['type'] == 'featured_image') {
									$image_url = $this->transform(@$field_content[0], $field, $data['fields'], $variables, $images, $gallery, $data, array(), $post_url, $task, $post);
									$image_name = basename($image_url);
									if (isset($used_images[$image_url])) {
										$featured_image = $used_images[$image_url];
									} else {
										$filename = false;
										if (@$task['filename']) {
											$filename = $this->process_filename($task['filename'], @$image_url, @$post['post_title']);
										}
										$featured_image = $this->upload_image($image_url, $post['ID'], $field, $data['feedURL'] ? $data['feedURL'] : $data['contentURL'], 'id', $filename);
									}
									if ($featured_image != -1) {
										set_post_thumbnail($post['ID'], $featured_image);
										wp_update_post(
											array(
												'ID' => $featured_image,
												'post_parent' => $post['ID']
											)
										);
										/**
										 * Insert Image Meta Details
										 */
										if (@$field['imgtitle'] == 'true') {
											if ($task['parse_method'] == 'xpath') {
												$imgtitle = $this->parse_xpath($post_html, $field['path'], 'attr:title');
												if (!empty($imgtitle)) {
													if (is_array($imgtitle) && isset($imgtitle[0])) {
														$imgtitle = $imgtitle[0];
													}
													wp_update_post(array(
														'ID' => $featured_image,
														'post_title' => $imgtitle,
													));
												}
											}
										}
										if (@$field['imgalt'] == 'true') {
											if ($task['parse_method'] == 'xpath') {
												$imgalt = $this->parse_xpath($post_html, $field['path'], 'attr:alt');
												if (!empty($imgalt)) {
													if (is_array($imgalt) && isset($imgalt[0])) {
														$imgalt = $imgalt[0];
													}
													update_post_meta($featured_image, '_wp_attachment_image_alt', $imgalt);
												}
											}
										}
									}
								} elseif ($field['type'] == 'gallery') {
									foreach ($field_content as $key => $field_url) {
										$field_url = $this->transform(@$field_url, $field, $data['fields'], array(), array(), array(), $data, array(), $post_url, $task, $post);
										$filename = false;
										if (@$task['filename']) {
											$filename = $this->process_filename($task['filename'], $field_url, @$post['post_title']);
										}
										if (isset($used_images[$field_url]) && @$used_images[$field_url]) {
											$gallery[] = $used_images[$field_url];
										} else {
											$field_url = is_array($field_url) ? $field_url : array($field_url);
											foreach ($field_url as $key => $field_url_array_part) {
												$uploaded_image = $this->upload_image(@$field_url_array_part, $post['ID'], $field, $data['feedURL'], 'id', $filename);
												$gallery[] = $uploaded_image;
												if ($field_url_array_part && $uploaded_image) {
													$image_name = basename($field_url_array_part);
													$used_images[$field_url_array_part] = $uploaded_image;
												}
											}
										}
									}
									if ($gallery) {
										if ($task['post_type'] == 'product') {
											update_post_meta($post['ID'], '_product_image_gallery', implode(',', $gallery));
										}
									}
								} else if ($field['type'] == 'image') {
									$filename = false;
									if (@$task['filename']) {
										$filename = $this->process_filename($task['filename'], @$field_content[0], @$post['post_title']);
									}
									$attachment_url = $this->transform(@$field_content[0], $field, $data['fields'], array(), array(), array(), $data, array(), $post_url, $task, $post);
									$extract_method = 'HTMLID';
									if (isset($field['extract']) && @$field['extract'] == 'id') {
										$extract_method = 'URLID';
									}
									if (isset($field['extract']) && @$field['extract'] == 'url') {
										$extract_method = 'URL';
									}
									if (isset($used_images[$attachment_url]) && @$used_images[$attachment_url]) {
										$processed_image = @$used_images[$attachment_url];
									} else {
										$processed_image = $this->upload_image($attachment_url, $post['ID'], $field, $data['feedURL'], $extract_method, $filename);
										$used_images[$attachment_url] = $this->get_last_file_id;
									}
									if (isset($field['extract']) && @$field['extract'] == 'url') {
										$images[$field['name']] = @$processed_image;
										if ($images[$field['name']]) {
											$attach_images[] = @$processed_image;
										}
									} else if (isset($field['extract']) && @$field['extract'] == 'id') {
										$images[$field['name']] = @$processed_image[0];
										if ($images[$field['name']]) {
											$attach_images[] = @$processed_image[0];
										}
									} else {
										$images[$field['name']] = @$processed_image[1];
										if ($images[$field['name']]) {
											$attach_images[] = @$processed_image[0];
										}
									}
								}
							}
						} else {
							continue;
						}
					}

					if($post_author != -1){
                        $arg = array(
                            'ID' => $post['ID'],
                            'post_author' => $post_author
                        );

                        wp_update_post( $arg );
                    }

                    if($post_slug != -1){
                        $arg = array(
                            'ID' => $post['ID'],
                            'post_name' => sanitize_title_with_dashes($post_slug)
                        );

                        wp_update_post( $arg );
                    }

                    //update custom fields
                    if($custom_fields){
                        foreach ($custom_fields as $key => $value) {
                            if(@$field_by_type[$key]['isJSON'] == 'true'){
                                update_post_meta( $post['ID'], $key, $value[$field_by_type[$key]['name']] );
                            }else if(is_array($value)){
                                $items = $value;

                                foreach ($items as $custom_field_name => $custom_field_values) {
                                    update_post_meta( $post['ID'], $key, implode('|', $custom_field_values) );
                                }
                            }else{
                                update_post_meta( $post['ID'], $key, $value );
                            }

                            if($task['post_type'] == 'product' && $key == '_product_attributes'){
                                //needs name
                                $items = $value;
                                $attributes = array();

                                foreach ($items as $item_name => $values) {
                                    if(count($values) > 1){
                                        $values = array_unique($values);
                                    }else{
                                        $values = array_unique($values[0]);
                                    }

                                    foreach ($values as $tag_key => $tag_value) {
                                        wp_set_object_terms($post['ID'], $tag_value, $item_name, true);
                                    }

                                    $attributes[] = array (
                                        'name' => htmlspecialchars( stripslashes( $item_name ) ),
                                        'value' => implode('|', $values),
                                        'position' => 1,
                                        'is_visible' => 1,
                                        'is_variation' => 1,
                                        'is_taxonomy' => 0
                                    );
                                }

                                update_post_meta( $post['ID'], $key, $attributes );
                            }
                        }
                    }

                    //All category functions
                    $post_categories = array($task['category_id']);

                    if(@$task['category_ids']){
                        $category_ids    = json_decode($task['category_ids']);
                        $post_categories = array();

                        foreach ($category_ids as $key => $category_id) {
                            $post_categories[] = (int) $category_id;
                        }
                    }

                    if($categories){
                        foreach ($categories as $key => $category_name) {
                            if (is_array($category_name)) {
                                $category_name = array_filter($category_name);
                                foreach ($category_name as $catName) {
                                    $category_exist = term_exists($catName, 'category');
                                    if( 0 !== $category_exist && null !== $category_exist){
                                        $category = get_term_by( 'name', $catName, 'category' );
                                    }else{
                                        $category = wp_insert_term(
                                            $catName,
                                            'category',
                                            array(
                                              'description' => '',
                                              'slug'        => sanitize_title($catName)
                                            )
                                        );
                                    }
                                    if(!is_wp_error($category) ){
                                        if(isset($category->term_id)){
                                            $post_categories[] = $category->term_id;
                                        }else{
                                            $post_categories[] = $category['term_id'];
                                        }
                                    }
                                }
                            } else {
                                $category_exist = term_exists($category_name, 'category');
                                if( 0 !== $category_exist && null !== $category_exist){
                                    $category = get_term_by( 'name', $category_name, 'category' );
                                }else{
                                    $category = wp_insert_term(
                                        $category_name,
                                        'category',
                                        array(
                                          'description' => '',
                                          'slug'        => sanitize_title($category_name)
                                        )
                                    );
                                }
                                if(!is_wp_error($category) ){
                                    if(isset($category->term_id)){
                                        $post_categories[] = $category->term_id;
                                    }else{
                                        $post_categories[] = $category['term_id'];
                                    }
                                }
                            }
                        }
                    }

                    if($post['ID'] && $post_categories){
                        wp_set_post_categories( $post['ID'], $post_categories );
                    }

                    if($post['ID'] && $post_categories && $task['post_type'] == 'product'){
                        wp_set_object_terms($post['ID'], $post_categories, 'product_cat');
                    }
                    
                    //Assign taxonomy to custom post type
                    if($post['ID'] && $task['post_type'] != 'product' && $task['post_type'] != 'post'){                        
                        $taxonomy_objects = get_object_taxonomies( $task['post_type'], 'objects' );
                        if($taxonomy_objects){
                            $term_ids_arr = array();
                            foreach ($taxonomy_objects as $taxonomy_name => $taxonomy_value) {
                                $terms = get_terms( $taxonomy_name, array('hide_empty' => false) );                                
                                if($terms){
                                    foreach ($terms as $single_term => $single_term_obj) {
                                        if(in_array($single_term_obj->term_id, $post_categories) ){
                                            $term_ids_arr[] = $single_term_obj->term_id;
                                        }
                                    }
                                }
                                if(!empty($term_ids_arr))
                                    wp_set_object_terms($post['ID'], $term_ids_arr, $taxonomy_name);
                            }
                        }
                    }
                    
                    if($post['ID'] && $task['post_type'] == 'product' && @$tag_taxonomies['product_tag']){
                        wp_set_object_terms($post['ID'], $tag_taxonomies['product_tag'], 'product_tag');
                    }

                    if($post['ID'] && $product_type && $task['post_type'] == 'product'){
                        wp_set_object_terms( $post['ID'], $product_type, 'product_type');
                    }

                    //All tag functions
                    if($tag_taxonomies){
                        foreach ($tag_taxonomies as $tag_taxonomy => $tags) {
                            $taxonomy_list = array();

                            foreach ($tags as $tag_index => $tag_name) {
                                $tag_exist = term_exists($tag_name, $tag_taxonomy);

                                if( 0 !== $tag_exist && null !== $tag_exist){
                                    $tag = get_term_by( 'name', $tag_name, $tag_taxonomy );
                                }else{
                                    $tag = wp_insert_term(
                                        $tag_name,
                                        $tag_taxonomy,
                                        array(
                                          'description' => '',
                                          'slug'        => sanitize_title($tag_name)
                                        )
                                    );
                                }

                                if(!is_wp_error($tag)){
                                    if(isset($tag->term_id)){
                                        $taxonomy_list[] = $tag->term_id;
                                    }
                                }
                            }

                            wp_set_object_terms( $post['ID'], $taxonomy_list, $tag_taxonomy );
                        }
                    }

                    //attach images later
                    if($attach_images && @$post['ID']){
                        foreach ($attach_images as $key => $image_id) {
                            //wordpress attach image to post

                            if($image_id){
                                $data = array(
                                    'ID' => $image_id,
                                    'post_parent' => $post['ID']
                                );

                                wp_update_attachment_metadata( $image_id, $data );
                            }
                        }
                    }
                    
                    $timezone_format = _x('Y-m-d H:i:s', 'timezone date format');
                    
                    return array(
                        'postTitle'      => isset($post['post_title']) ? $post['post_title'] : '',
                        'postId'         => @$post['ID'],
                        'post'           => $post,
                        'featured_image' => $featured_image,
                        'images'         => $images,
                        'timestamp'      => time(),
                        'date'           => date_i18n( $timezone_format ),
                        'success'        => $success,
                        'is_unique'      => @$uniqueness,
                        'excluded'       => @$excluded,
                        'insert_error'   => $this->insert_error
                    );
                }else{
                    return false;
                }
            }

            public function process_task($task, $manually_triggered = false){
                //Set timelimit for delays for 30 minutes, user can change this value.
                //All sub functions has timeout, so it won't reach the time limit.

                $enable_errors = $this->with_default('enable_errors', false, $this->options);

                if(isset($enable_errors) && $enable_errors == 'true'){
                    error_reporting(E_ERROR);
                    ini_set('display_errors', 1);
                }

                $disable_memory_limit = $this->with_default('disable_memory_limit', false, $this->options);

                if(isset($disable_memory_limit) && $disable_memory_limit == 'true'){
                    ini_set("memory_limit", -1);
                }

                set_time_limit(1800);

                $output = array();
                $data   = json_decode($task['data'], true);

                //check if task used by some process
                if($task['running'] == 1){
                    return false;
                }

                //check if task is active
                if($manually_triggered == false && $task['active'] == 0){
                    return false;
                }

                //check if it's completed fully
                if((int) $data['connection']['total_run'] > 0 && (int) $data['connection']['total_run'] <= (int) $task['count_run']){
                    //stop this task
                    $request_options = array(
                        'request' => 'stop_task',
                        'hash'    => $task['hash']
                    );

                    $output = $this->service($request_options);

                    return false;
                }

                $source_connection   = false;
                $increase_task_limit = false;
                $collected_urls      = array();
                $next_page_path_defined = false;
                $page_increased = false;

                //inform scraper about task start
                $this->start_process($task);
				if (isset($data['urlType']) && $data['urlType'] == 'rss') {
					$last_index = 0;
					$last_index = (int) $task['last_index'];

					$contentURL = $data['contentURL'];
					$call_data = array(
						'user_agent'  => $data['connection']['user_agent'],
						'redirection' => 5,
						'sslverify'   => false,
						'timeout'     => 5,
						'cookie'     => $data['connection']['cookie'],
						'proxy'     => $data['connection']['proxy'],
						'ajaxwait'    => isset($data['connection']['ajaxwait']) ? $data['connection']['ajaxwait'] : 1,
					);
					$feed_html = $this->get($contentURL, $call_data);
					$xml = new SimpleXMLElement($feed_html, LIBXML_NOCDATA);
					$items = $xml->xPath('//channel/item');
					foreach ($items as $k => $v) {
						if($last_index <= $k){
							if (isset($task['task_limit']) && (int) @$task['task_limit'] > 0 && @$task['task_limit'] + $last_index <= $k) {

							} else {
								$rss_item = '';
								foreach ($v as $key => $item) {
									$rss_item .= '<div class="meta_tag">';
									$rss_item .= '<a href="javascript:;" class="item_' . $key . '">' . $this->showText($item) . '</a>';
									$rss_item .= '</div>';
								}
								$process_result = $this->process_content($rss_item, $data, $task, $manually_triggered);
								if ($process_result) {
									$output[] = $process_result;
								}
							}
						}
					}
				} else {
					//is single post?
					if($data['singlePost'] == 'true'){
						//check if there is bulk URL list
						if(isset($data['other']) && @$data['other'] && @$data['other']['bulkURL'] && strlen($data['other']['bulkURL']) > 5){
							$url_list  = array(@$data['contentURL']);
							$bulk_urls = explode("\n", $data['other']['bulkURL']);

							foreach ($bulk_urls as $key => $bulk_url) {
								$url_list[] = $bulk_url;
							}

							if(isset($task['last_index']) && @$task['last_index']){
								$last_index = (int) $task['last_index'];
							}else{
								$last_index = 0;
							}

							$feed_items  = array();

							$count_index = $task['task_limit'] ? (int) $task['task_limit'] : 100;

							for($i=$last_index;$i<($last_index + $count_index);$i++){
								if(isset($url_list) && $this->is_url($url_list[$i])){
									$feed_items[] = @$url_list[$i];
								}
							}

							//Items will be processed
							foreach ($feed_items as $key => $post_url) {
								$output[] = $this->process_content($post_url, $data, $task, $manually_triggered);
							}
						}else{
							$output[] = $this->process_content($data['contentURL'], $data, $task, $manually_triggered);
						}
					}else{
						if($task['current_page_url']){
							$feedURL = $task['current_page_url'];
							$baseURL = false;
						}else{
							$feedURL = $data['feedURL'];
							$baseURL = false;
						}

						if($feedURL){
							//feed method
							$feed_html  = $this->get_url($feedURL, $data, $baseURL);
							$feed_items = $this->parse_xpath($feed_html, $data['feed']['path'], 'deep_link');

							if($feed_html){
								$source_connection = true;
							}

							$last_index = 0;

							$last_index = (int) $task['last_index'];

							$collected_urls = $feed_items;

							//Items will be processed
							foreach ($feed_items as $key => $post_url) {
								if($last_index <= $key){
									if(isset($task['task_limit']) && (int) @$task['task_limit'] > 0 && @$task['task_limit'] + $last_index <= $key){

									}else{
										$post_url = $this->clean_url($post_url, $baseURL);
										$output[] = $this->process_content($post_url, $data, $task, $manually_triggered);
									}
								}
							}
						}
					}
				}

                //check posts for tracking
                if(isset($task['delete_post']) && @$task['delete_post'] == '1' && $task['post_type']){
                    $last_posts = $this->get_latest_posts($task['post_type'], -1);

                    foreach ($last_posts as $key => $post) {
                        if(isset($post['task_hash']) && $post['task_hash']){
                            if(@$post['post_source_url'][0] && $this->get_url(@$post['post_source_url'][0], $data, $baseURL)){
                                //Post is okay
                            }else{
                                //perform delete function

                                if($post['id'] && isset($task['delete_method'])){
                                    if(@$task['delete_method'] == 'delete'){
                                        wp_delete_post($post['id']);
                                    }else if(@$task['delete_method'] == 'status_draft'){
                                        wp_update_post(array(
                                            'ID'            =>  $post['id'],
                                            'post_status'   =>  'draft'
                                        ));
                                    }else if(@$task['delete_method'] == 'status_publish'){
                                        wp_update_post(array(
                                            'ID'            =>  $post['id'],
                                            'post_status'   =>  'publish'
                                        ));
                                    }
                                }
                            }
                        }
                    }
                }

                $nextPageFound = 0;

                if(@$data['nextPage']['path']){
                    $nextPageFound = 1;
                    $next_page_path_defined = true;

                    $feed_html  = $this->get_url($feedURL, $data);

                    if($feed_html){
                        $source_connection = true;
                    }

                    //find next link
                    $field_content = $this->parse_xpath($feed_html, $data['nextPage']['path'], 'deep_link');
                    $nextPageURL = @$field_content[0];

                    if($nextPageURL){
                        $nextPageFound = 2;
                    }

                    $feedURL     = $this->clean_url($feedURL, $baseURL);
                    $nextPageURL = $this->clean_url($nextPageURL, $feedURL);

                    if(count($collected_urls) <= (int) $task['last_index'] && $nextPageURL){
                        $this->increase_page($task, $nextPageURL);
                    }
                }else if(count($collected_urls) <= (int) $task['last_index']){
                    $nextPageFound = 1;
                }

                $this->finish_process($task, $output);

                if($nextPageFound == 1 && isset($task['reset_task']) && $task['reset_task'] == '1'){
                    $this->reset_indexes($task);
                }

                //clear false processes
                $result = array();

                foreach ($output as $key => $value) {
                    if($value !== false){
                        $result[] = $value;
                    }
                }

                if(count($result) == 0){
                    return array(
                        'source_connection' => $source_connection,
                        'collected_urls'    => $collected_urls,
                        'http_status_code'  => $this->last_http_status_code,
                        'last_index'        => $task['last_index'],
                        'next_page_path_defined' => $next_page_path_defined,
                        'insert_error' => $this->insert_error
                    );
                }else{
                    return $output;
                }
            }

            public function upload_image($url, $parent_id, $parameters, $base_url, $output = 'id', $filename = false){
                $url = $this->clean_url($url, $base_url);
				$image = $url;
				$post_images = array();
				if ($parent_id > 0) {
					$post_images = get_post_meta($parent_id, 'scraper_media', true);
					$post_images = is_array($post_images) ? $post_images : array();
				}

				if (in_array($url, $post_images)) {
					$attach_id = array_search($url, $post_images);
				} else {
					$data = array(
						'redirection' => 5,
						'sslverify' => false,
						'timeout' => 5,
					);

					$get = wp_remote_get($image, $data);
					$type = wp_remote_retrieve_header($get, 'content-type');

					if (!$type && !$get)
						return false;

					$type = $type ? $type : 'image/jpeg';
					$image_name = basename($image);
					$image_parse = explode('?', $image_name);

					if (count($image_parse) > 1) {
						$image_name = $image_parse[0];
					}

					$image_name = $image_name ? $image_name : 'image_' . rand(0, 10000);

					if (isset($filename) && @$filename) {
						$extension = pathinfo($image_name, PATHINFO_EXTENSION);

						if (
							strpos($filename, '.jpg') > -1 ||
							strpos($filename, '.png') > -1 ||
							strpos($filename, '.jpeg') > -1 ||
							strpos($filename, '.png') > -1
						) {
							$processed_name = sanitize_file_name($filename);
							$image_name = basename($processed_name) . '-' . rand(0, 100) . '.' . $extension;
						} else {
							if (!$extension) {
								$extension = 'jpg';
							}

							$image_name = sanitize_file_name($filename) . '-' . rand(0, 100) . '.' . $extension;
						}
					}

					$mirror = wp_upload_bits($image_name, '', wp_remote_retrieve_body($get));

					$attachment = array(
						'post_title' => $image_name,
						'post_mime_type' => $type
					);

					if (!$mirror['file']) {
						return false;
					}

					$attach_id = wp_insert_attachment($attachment, $mirror['file'], $parent_id);
					require_once(ABSPATH . 'wp-admin/includes/image.php');
					$attach_data = wp_generate_attachment_metadata($attach_id, $mirror['file']);
					wp_update_attachment_metadata($attach_id, $attach_data);
					/**
					 * Add Media Files into Parent Post Meta Data
					 */
					if ($parent_id > 0 && $attach_id) {
						$post_images[$attach_id] = $url;
						update_post_meta($parent_id, 'scraper_media', $post_images);
					}
				}

				$this->get_last_file_id = $attach_id;

				if ($output == 'URLID') {
					return array($attach_id, wp_get_attachment_url($attach_id));
				} else if ($output == 'HTMLID') {
					return array($attach_id, wp_get_attachment_image($attach_id, 'full'));
				} else if ($output == 'HTML') {
					return wp_get_attachment_image($attach_id, 'full');
				} else if ($output == 'URL') {
					return wp_get_attachment_url($attach_id);
				} else {
					return $attach_id;
				}
            }

            public function parse_regex($html, $path, $field){
                $output = array();

                preg_match_all('/'.$path.'/m', $html, $matches);

                if($field['regexIndex'] == -1){
                    foreach ($matches[1] as $key => $value) {
                        $output[] = $value;
                    }
                }else{
                    $output[] = $matches[1][$field['regexIndex']];
                }

                return $output;
            }

            public function parse_xpath($html, $path, $prop){
                if($path == '-'){
                    return array();
                }else if($html){
                    $output = array();

                    libxml_use_internal_errors(true);
                    $dom   = new DomDocument;
                    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
                    $xpath = new DomXPath($dom);
                    $nodes = $xpath->query($path);

                    $props = explode(':', $prop);

                    if(count($props) > 1){
                        $propType  = $props[0];
                        $propValue = $props[1];

                        if($propValue == 'original-href'){
                            $propValue = 'href';
                        }
                    }else{
                        $propType  = false;
                    }

                    foreach ($nodes as $i => $node) {
                        if($prop == 'innerHTML'){
                            $output[] = $node->C14N();
                        }else if($prop == 'deep_link'){
                            $output[] = $node->getAttribute('href');
                        }else if($propType == 'attr'){
                            $output[] = $node->getAttribute($propValue);
                        }else if($prop == 'href'){
                            $output[] = $node->getAttribute('href');
                        }else{
                            $output[] = $node->nodeValue;
                        }
                    }

                    return $output;
                }else{
                    return array();
                }
            }

            public function clean_url($url, $base_url = ''){
                if(substr($url, 0, 2) == '//'){
                    $domain_parse = parse_url($base_url);

                    $url = @$domain_parse['scheme']. ':' .$url;
                }else if($base_url && strpos($url, 'http') === false){
                    $domain_parse = parse_url($base_url);

                    if(substr($url, 0, 1) == '/'){
                        $url = $domain_parse['scheme']. '://' . $domain_parse['host'] . $url;
                    }else if(count(explode('/', $base_url)) > 4){
                        $query_params = explode('?', $url);
                        $query_params = $query_params[0];

                        if(count(explode('/', $query_params)) == 1){
                            //clean last part from URL
                            $split_base_url = explode('?', $base_url);
                            $split_base_url = explode('/', $split_base_url[0]);
                            $split_base_url = array_slice($split_base_url, 0, -1);
                            $url = implode('/', $split_base_url) . '/' . $url;
                        }else{
                            $url = $base_url . '/' . $url;
                        }
                    }else{
                        $url = $domain_parse['scheme']. '://' . $domain_parse['host'] . '/' . $url;
                    }
                }else{
                    $query_params_0 = explode('?', $url);
                    $query_params_1 = explode('?', $base_url);

                    $last_part_0 = end($query_params_0);
                    $last_part_1 = end($query_params_1);

                    if(count($query_params_0) > 1 && count($query_params_1) > 1){
                        $url = $query_params_0[0] . '?' . $last_part_0;
                    }
                }

                if(substr($url, 0, 3) == '://'){
                    $url = 'http'.$url;
                }

                if(strpos($url, 'http') === false){
                    $url = 'http://'.$url;
                }

                return $url;
            }

            public function _isCurl(){
                return function_exists('curl_version');
            }

            public function get($url, $data=array()){
                $handle = curl_init($url);

                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($handle, CURLOPT_HTTPPROXYTUNNEL, 1);

                if (isset($data['user_agent']) && !empty($data['user_agent'])) {
					curl_setopt($handle, CURLOPT_USERAGENT, $data['user_agent']);
				} else {
					curl_setopt($handle, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36');
				}
				if (isset($data['cookie']) && !empty($data['cookie'])) {
					//curl_setopt($handle, CURLOPT_COOKIESESSION, true);
					curl_setopt($handle, CURLOPT_HTTPHEADER, array("Cookie: " . $data['cookie']));
				}
				if (isset($data['proxy']) && !empty($data['proxy'])) {
					curl_setopt($handle, CURLOPT_PROXY, @$data['proxy']);
				}

				curl_setopt($handle,CURLOPT_ENCODING , "gzip");

                $response = curl_exec($handle);
                $info     = curl_getinfo($handle);
                $enable_errors = $this->with_default('enable_errors', false, $this->options);
                if(isset($enable_errors) && $enable_errors == 'true'){
                    var_dump('Response Dump : ');
                    var_dump($info);
                }

                $this->status    = $info['http_code'];
                $this->load_time = $info['total_time'];

                curl_close($handle);

                return $response;
            }
			
			public function getAjax($url, $data){
				
				$ajaxUrl = $this->service_url . 'passthru.php?url='. urlencode($url);
				$purchase_code = $this->with_default('purchase_code', false, $this->options);
                $domain = $this->get_licence_domain();
                $data['hash'] = (isset($_POST['data']['hash']) ? $_POST['data']['hash'] : '');
                $data['purchase_code'] = $purchase_code;
				$data['domain'] = $domain;
				
				$handle = curl_init($ajaxUrl);

                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($handle, CURLOPT_HTTPPROXYTUNNEL, 1);
				curl_setopt($handle, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36');
				curl_setopt($handle,CURLOPT_ENCODING , "gzip");
				
				curl_setopt($handle, CURLOPT_POSTFIELDS, $data);

				curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 1500);
				curl_setopt($handle, CURLOPT_TIMEOUT, 1800);

				$response = curl_exec($handle);
                $info     = curl_getinfo($handle);
                $enable_errors = $this->with_default('enable_errors', false, $this->options);
                if(isset($enable_errors) && $enable_errors == 'true'){
                    var_dump('Response Dump : ');
                    var_dump($info);
                }

                $this->status    = $info['http_code'];
                $this->load_time = $info['total_time'];

                curl_close($handle);

                return $response;
			}

            public function get_url($url, $data = array(), $base_url = ''){
                $url = $this->clean_url($url, $base_url);
                $url = $this->rel2abs($url, $base_url);
				
				$fetchajax = isset($data['connection']['fetchajax']) ? $data['connection']['fetchajax'] : 'false';
				$call_data = array(
					'user_agent'  => $data['connection']['user_agent'],
					'redirection' => 5,
					'sslverify'   => false,
					'timeout'     => 5,
					'cookie'     => $data['connection']['cookie'],
					'proxy'     => $data['connection']['proxy'],
					'ajaxwait'    => isset($data['connection']['ajaxwait']) ? $data['connection']['ajaxwait'] : 1,
				);
				
				if ($fetchajax == 'true') {
					$result = $this->getAjax($url, $call_data);
				} else {
					if(!$this->_isCurl()){
						$result = wp_remote_get($url, $call_data);

						if ( !is_wp_error($result) ) {
							if($result['response']['code'] == 200){
								$result = $result['body'];
							}else{
								return false;
							}
						}else{
							return false;
						}
					}else{
						$result = $this->get($url, $call_data);
					}
				}

                if($result){
                    $doc = new DomDocument();
                    @$doc->loadHTML(mb_convert_encoding(@$result, "HTML-ENTITIES", mb_detect_encoding(@$result)));
                    $xpath = new DOMXPath($doc);

                    preg_match_all('/<base href="(.*?)"(.*?)\/>/', @$result, $matches);

                    if(@$matches[1][0]){
                        $relative_url = $matches[1][0];
                    }else{
                        $relative_url = $url;
                    }

                    //Proxify any of these attributes appearing in any tag.
                    $proxifyAttributes = array("href", "src");
                    foreach($proxifyAttributes as $attrName) {
                        foreach($xpath->query('//*[@' . $attrName . ']') as $element) { //For every element with the given attribute...
                        $attrContent = $element->getAttribute($attrName);
                        if ($attrName == "href" && (stripos($attrContent, "javascript:") === 0 || stripos($attrContent, "mailto:") === 0)) continue;
                            $attrContent = $this->rel2abs($attrContent, $relative_url);

                            if(strpos($attrContent, 'scraper.site') > -1){

                            }else if(strpos($attrContent, 'https') > -1){

                            }else if(strpos($attrContent, 'data:') == 0){

                            }
                            
                            $element->setAttribute($attrName, $attrContent);
                        }
                    }

                    $body = $doc->saveHTML();

                    return $body;
                }else{
                    return false;
                }
            }

            public function wp_scraper_enqueue_scripts(){
                wp_enqueue_script( 'scraper-service', plugins_url( '/assets/js/scraper.js?v=' . SCRAPER_PLUGIN_VERSION, __FILE__ ));
                wp_enqueue_style( 'admin_css', plugins_url( '/assets/css/scraper.css?cache=1.0&v=' . SCRAPER_PLUGIN_VERSION, __FILE__ ));

                wp_localize_script( 'scraper-service', 'scraper_service', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'purchase_code' => $this->get_purchase_code()
                ));
            }

            public function wp_scraper_service(){
				global $SCRAPER_DB;
                header('Access-Control-Allow-Origin: *');
                header('Content-Type: application/json');

                $output  = array();
                $request = isset($_POST['request']) ? $_POST['request'] : '';
                $code    = isset($_POST['purchase_code']) ? @$_POST['purchase_code'] : '';

                if($code != $this->get_purchase_code()){
                    exit;
                }

                if($request == 'get_information'){
                    //Service for displaying categories and options on visual editor
                    $output['categories']    = $this->get_categories();
                    $output['post_types']    = $this->get_post_types();
                    $output['accounts']      = $this->get_account_names();
                    $output['latest_posts']  = $this->get_latest_posts($output['post_types']);
                    $output['custom_fields'] = $this->get_custom_fields($output['latest_posts']);
                }

                if($request == 'start_task' && isset($_POST['data']) && isset($_POST['data']['hash'])){
                    $data = array(
                        'request' => 'start_task',
                        'hash' => $_POST['data']['hash']
                    );

                    $output = $this->service($data);
                }

                if($request == 'stop_task' && isset($_POST['data']) && isset($_POST['data']['hash'])){
                    $data = array(
                        'request' => 'stop_task',
                        'hash' => $_POST['data']['hash']
                    );

                    $output = $this->service($data);
                }

                if($request == 'get_output_log' && isset($_POST['data']) && isset($_POST['data']['hash'])){
                    $data = array(
                        'request' => 'get_output_log',
                        'hash'    => $_POST['data']['hash']
                    );
					$result = $SCRAPER_DB->get_last_log($_POST['data']['hash']);
					$output = array('request' => 'get_output_log', 'success' => true, 'results' => $result);
                }

                if($request == 'trigger_task' && isset($_POST['data']) && isset($_POST['data']['hash'])){
                    $task    = $this->get_task($_POST['data']['hash']);
                    $results = $this->process_task($task, true);
                    $output = array('request' => 'trigger_task', 'success' => true, 'results' => $results);
				}

                if($request == 'clone_task' && isset($_POST['data']) && isset($_POST['data']['hash'])){
                    $data = array(
                        'request' => 'clone_task',
                        'hash' => $_POST['data']['hash']
                    );

                    $output = $this->service($data);
                }

                if($request == 'reset_task' && isset($_POST['data']) && isset($_POST['data']['hash'])){
                    $data = array(
                        'request' => 'reset_task',
                        'hash' => $_POST['data']['hash']
                    );

                    $output = $this->service($data);
                }

                if($request == 'delete_task' && isset($_POST['data']) && isset($_POST['data']['hash'])){
                    $data = array(
                        'request' => 'delete_task',
                        'hash' => $_POST['data']['hash']
                    );

                    $output = $this->service($data);
                }

                echo json_encode($output);
                exit;
            }
            
            public function scraper_view_update(){
                $view = isset($_POST['view']) ? trim($_POST['view']) : 'Simple View';
                update_option('_scraper_view', $view);
                echo 'success';
                exit();
            }

            public function get_scheduled_tasks($filterByTime = false){
                $output = array();
                $data   = array(
                    'request' => 'get_tasks',
                    'filterByTime' => $filterByTime
                );

                $service_response = $this->service($data);

                if($service_response){
                    $output = $service_response;
                }

                return $output;
            }

            /**
             * Activate the plugin
             */
            public static function activate(){

            }

            /**
             * Deactivate the plugin
             */
            public static function deactivate(){
                
            }

            public function get_purchase_code(){
                $purchase_code = $this->with_default('purchase_code', false, $this->options);

                return $purchase_code;
            }

            public function next_time_string($task = array()) {
				if (empty($task)) {
					return '-';
				}
				$last_complete = (isset($task['last_complete']) && !empty($task['last_complete']) ? $task['last_complete'] : date('Y-m-d H:i:s'));
				$run_interval = (isset($task['run_interval']) ? $task['run_interval'] : 0);
				$currenttime = (isset($task['currenttime']) && !empty($task['currenttime']) ? $task['currenttime'] : date('Y-m-d H:i:s'));
				$next_in_seconds = $this->seconds[$run_interval];

				$now = new DateTime($last_complete);
				$now->add(new DateInterval('PT' . $next_in_seconds . 'S'));

				$ago = new DateTime($currenttime);
				if ($ago > $now) {
					return 'In Queue...';
				}

				$diff = $now->diff($ago);
				if ($diff->y > 1) {
					return '-';
				}
				$diff->w = floor($diff->d / 7);
				$diff->d -= $diff->w * 7;
				$string = array(
					'y' => 'year',
					'm' => 'month',
					'w' => 'week',
					'd' => 'day',
					'h' => 'hour',
					'i' => 'minute',
					's' => 'second',
				);
				foreach ($string as $k => &$v) {
					if ($diff->$k) {
						$v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
					} else {
						unset($string[$k]);
					}
				}
				$string = array_slice($string, 0, 1);

				return $string ? implode(', ', $string) . ' later' : 'in seconds';
			}

            public function time_elapsed_string($datetime, $full = false) {
                $now = new DateTime;
                $ago = new DateTime($datetime);
                $diff = $now->diff($ago);
                $diff->w = floor($diff->d / 7);
                $diff->d -= $diff->w * 7;
                $string = array(
                    'y' => 'year',
                    'm' => 'month',
                    'w' => 'week',
                    'd' => 'day',
                    'h' => 'hour',
                    'i' => 'minute',
                    's' => 'seconds',
                );
                foreach ($string as $k => &$v) {
                    if ($diff->$k) {
                        $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
                    } else {
                        unset($string[$k]);
                    }
                }

                if($diff->y > 1){
                    return '-';
                }

                if (!$full) $string = array_slice($string, 0, 1);
                return $string ? implode(', ', $string) . ' ago' : 'a while ago';
            }

            public function time_duration($end, $start){
                if((strtotime($end) - strtotime($start)) < 0){
                    return '-';
                }else{
                    return (strtotime($end) - strtotime($start));
                }
            }

            /**
             *  API Gateway for scraper.site
             */
            public function service( $options ){
                $purchase_code = $this->with_default('purchase_code', false, $this->options);
                $domain = $this->get_licence_domain();

                $data = $options ? $options : array();

                $data['domain'] = $domain;
                $data['purchase_code'] = $purchase_code;

                $request = $this->service_url . '?request=' . $options['request'];
                $result  = wp_remote_post($request, array(
                        'method' => 'POST',
                        'body' => $data,
                        'headers' => array(
                            'Referer' => $domain,
                        )
                    )
                );

                if ( !is_wp_error($result) ) {
					if(is_array($result) && isset( $result['response']['code'] ) && @$result['response']['code']){
						$this->last_http_status_code = $result['response']['code'];
					}
                    if(is_array($result) && $result['response']['code'] == 200){
                        $this->service_log = json_decode(@$result['body'], true);
                        $this->setCache($options['request'], $this->service_log);
                        return $this->service_log;
                    }else{
                        return json_decode($this->getCache($options['request']), true);
                    }
                }else{
                    return json_decode($this->getCache($options['request']), true);
                }
            }

            public function plugin_menu_pages(){
                add_menu_page('Scraper', 'Scraper', 'manage_options', 'scraper_tasks', '', $this->plugin_url . '/assets/images/menu.icon.png', 26 );

                add_submenu_page('scraper_tasks', 'Scheduled Tasks', 'Scheduled Tasks', 'manage_options', 'scraper_tasks', array($this, 'scraper_pages') );

                add_submenu_page('scraper_tasks', 'Create New Task', 'Create New Task', 'manage_options', 'scraper_create', array($this, 'scraper_pages'));
                add_submenu_page('scraper_tasks', 'License and Settings', 'License and Settings', 'manage_options', 'scraper_license', array($this, 'scraper_pages') );
            }

            /**
             * Components
             */

            public function create_tab($title, $icon, $slug, $link = false){
                if( $link === true ){
                    echo '<a target="_blank" href="'.$slug.'" class="nav-tab '.($slug == $this->active_tab ? 'nav-tab-active' : '').'"><span class="dashicons dashicons-'.$icon.'"></span> '.$title.'</a>';
                }else {
                    echo '<a href="admin.php?page='.$slug.'" class="nav-tab '.($slug == $this->active_tab ? 'nav-tab-active' : '').'"><span class="dashicons dashicons-'.$icon.'"></span> '.$title.'</a>';
                }                
            }

            public function create_textbox($id, $value, $label = '', $placeholder = '', $type = 'text'){
                if($type == 'checkbox'){
                    if($value == 'true'){
                        $checked = true;
                    }else{
                        $checked = false;
                    }

                    $value = 'true';
                }else{
                    $checked = true;
                }

                ?>
                <label>
                    <input <?php echo $checked ? 'checked' : ''; ?> type="<?php echo $type; ?>" name="SCRAPER[<?php echo $id; ?>]" value="<?php echo $value; ?>" placeholder="<?php echo $placeholder; ?>">
                    <br>
                    <small><?php echo $label; ?></small>
                </label>
                <?php
            }

            //front end functions
            public function get_categories(){
                $output = array();

                //woocommerce
                $categories = get_categories(
                    array(
                        'orderby'    => 'name',
                        'order'      => 'ASC',
                        'hide_empty' => 0,
                        'taxonomy'   => 'product_cat'
                    )
                );

                foreach($categories as $category) {
                   $output[] = array('id' => $category->term_id, 'name' => $category->name, 'taxonomy' => 'product');
                }
                
                $args = array(
                    'public'   => true,
                    '_builtin' => false,
                 );

                 $output_str = 'names'; // names or objects, note names is the default
                 $operator = 'and'; // 'and' or 'or'

                 $post_types = get_post_types( $args, $output_str, $operator ); 
                 unset($post_types['qmn_log']);
                 unset($post_types['quiz']);
                 unset($post_types['product']);
                 unset($post_types['post']);
                 if($post_types){
                    foreach ($post_types as $value) {
                        $taxonomy_objects = get_object_taxonomies( $value, 'objects' );
                        if($taxonomy_objects){
                            foreach ($taxonomy_objects as $taxonomy_name => $taxonomy_value) {
                                $terms = get_terms( $taxonomy_name, array('hide_empty' => false) );                                
                                if($terms){
                                    foreach ($terms as $single_term => $single_term_obj) {
                                        $output[] = array('id' => $single_term_obj->term_id, 'name' => $single_term_obj->name, 'taxonomy' => $value);
                                    }
                                }
                            }
                        }
                    }                    
                 }                 
                 
                //normal post
                $categories = get_categories(
                    array(
                        'orderby'    => 'name',
                        'order'      => 'ASC',
                        'hide_empty' => 0,
                        'taxonomy'   => 'category'
                    )
                );

                foreach($categories as $category) {
                   $output[] = array('id' => $category->term_id, 'name' => $category->name, 'taxonomy' => 'post');
                }

                return $output;
            }

            public function get_post_types(){
                $output = array_values(get_post_types());

                return $output;
            }

            public function get_account_names(){
                $output = get_users( array( 'fields' => array( 'display_name', 'ID' ) ) );

                return $output;
            }

            public function oembed_handler($atts, $content = null, $param = null){
                extract(shortcode_atts(
                    array(
                        'style' => null,
                        'pos'   => null
                    ), $atts)
                );

                if($atts){
                    $hash = @$atts['task'];
                    $content = get_option('SCRAPER_shortcode_variable_' . $hash);

                    if(@$atts['key'] && isset($content[$atts['key']])){
                        return $content[$atts['key']];
                    }else{
                        return null;
                    }
                }else{
                    return null;
                }
            }

            public function get_latest_posts($types, $count = 1){
                $output = array();

                if($count == 1){
                    $count = SCRAPER_MAXIMUM_POST_COUNT;
                }

                $args = array( 'numberposts' => $count, 'post_type' => $types );
                $recent_posts = wp_get_recent_posts( $args );

                foreach ($recent_posts as $key => $post) {

                    if( isset($post['ID']) ){
                        $post_source_url = get_post_meta($post['ID'], '_scraper_post_source_url');
                    }else{
                        $post_source_url = false;
                    }

                    if( isset($post['ID']) ){
                        $task_hash = get_post_meta($post['ID'], '_scraper_task_hash');
                    }else{
                        $task_hash = false;
                    }

                    if( isset($post['ID']) ){
                        $product_sku = get_post_meta($post['ID'], '_sku');
                    }else{
                        $product_sku = false;
                    }
                    
                    $output[] = array('id' => $post['ID'], 'name' => $post['post_title'], 'post_source_url' => $post_source_url, 'product_sku' => $product_sku, 'task_hash' => $task_hash, 'post_type' => $post['post_type'] );
                }

                return $output;
            }

            public function get_custom_fields($latest_posts){
                $output = array(
                    '_product_attributes', '_sku', '_regular_price', '_sale_price', 'total_sales', '_weight', '_price', '_stock', '_manage_stock', '_backorders'
                );

                foreach ($latest_posts as $key => $item) {
                    $custom_fields = get_post_custom($item['id']);

                    foreach ($custom_fields as $key => $name) {
                        if(strpos($key, '_oembed') > -1){
                            
                        }else{
                            $output[] = $key;
                        }
                    }
                }

                $output = array_unique($output);

                return $output;
            }

            public function get_user_agent(){
                return 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36';
            }

            /**
             * Pages
             */

            public function messages() {
                $messages = array();

                if($this->active_tab == 'scraper_license' && isset($_POST['SCRAPER'])){
                    $data = array(
                        'request' => 'confirm_token'
                    );

                    $confirmation = $this->service($data);

                    if(!$confirmation['status']){
                        $messages[] = array(
                            'type' => 'error',
                            'text' => __('Please enter your purchase code correctly. It is not valid.', 'SCRAPER')
                        );
                    }

                    $check_translate = $this->check_translate();
                    if(isset($check_translate['error']['message'])){
                        $messages[] = array(
                            'type' => 'error',
                            'text' => __('Google Translate API : '. $check_translate['error']['message'], 'SCRAPER')
                        );
                    }
                }

                if ( count( $messages ) != 0 ) {
                    foreach ( $messages as $message ) {

                        if ( !isset ( $message['type'] ) || !isset ( $message['text'] ) )
                            continue;
                        ?>
                        <div class="<?php echo $message['type']; ?>">
                            <p><?php echo $message['text']; ?></p>
                        </div>
                    <?php }
                }
            }

            public function process_post_request(){
                if(isset($_POST['SCRAPER'])){
                    $this->options['SCRAPER_purchase_code'] = $_POST['SCRAPER']['SCRAPER_purchase_code'];
                    update_option( 'SCRAPER', $this->options );

                    /*$this->options['SCRAPER_spinner_code'] = $_POST['SCRAPER']['SCRAPER_spinner_code'];
                    update_option( 'SCRAPER', $this->options );*/

                    $this->options['SCRAPER_bitly_login'] = $_POST['SCRAPER']['SCRAPER_bitly_login'];
                    update_option( 'SCRAPER', $this->options );

                    $this->options['SCRAPER_bitly_key'] = $_POST['SCRAPER']['SCRAPER_bitly_key'];
                    update_option( 'SCRAPER', $this->options );

                    $this->options['SCRAPER_google_translate'] = $_POST['SCRAPER']['SCRAPER_google_translate'];
                    update_option( 'SCRAPER', $this->options );

                    $this->options['SCRAPER_yandex_translate'] = $_POST['SCRAPER']['SCRAPER_yandex_translate'];
                    update_option( 'SCRAPER', $this->options );

                    $this->options['SCRAPER_deepl_translate'] = $_POST['SCRAPER']['SCRAPER_deepl_translate'];
                    update_option( 'SCRAPER', $this->options );

                    $this->options['SCRAPER_enable_errors'] = @$_POST['SCRAPER']['SCRAPER_enable_errors'];
                    update_option( 'SCRAPER', $this->options );

                    $this->options['SCRAPER_disable_memory_limit'] = @$_POST['SCRAPER']['SCRAPER_disable_memory_limit'];
                    update_option( 'SCRAPER', $this->options );
                }
            }

            public function scraper_pages(){
            ?>
                <div id="wpscraper-admin-wrapper" class="wrap">
                    <h1><?php _e('Scraper Settings', 'SCRAPER') ?></h1>

                    <?php $this->process_post_request(); ?>
                    <?php $this->messages(); ?>

                    <br>
                    <h2 class="nav-tab-wrapper">
                        <?php
                            $this->create_tab('Scheduled Tasks', 'flag', 'scraper_tasks');
                            $this->create_tab('Create New Task', 'plus', 'scraper_create');
                            $this->create_tab('License and Settings', 'admin-plugins', 'scraper_license');
                            $this->create_tab('Documentation', 'media-document', 'https://support.wpbots.net/documentation/',true);
                        ?>
                    </h2>

                    <?php
                        //Create page for settings panel, it doesn't contain form.
                        if($this->active_tab == 'scraper_tasks'){
                            $this->render_scraper_tasks();
                        }else if($this->active_tab == 'scraper_create'){
                            $this->render_scraper_create();
                        }else if($this->active_tab == 'scraper_license'){
                            $this->render_scraper_license();
                        }else{
                            echo 'Page not found!';
                        }
                    ?>
                </div>
            <?php
            }

            public function get_licence_domain(){
                $domain        = get_site_url();

                return $domain;
            }

            public function render_scraper_create(){
                $domain = $this->get_licence_domain();
                $hash   = isset($_GET['hash']) ? $_GET['hash'] : '';
                $admin_email = get_option('admin_email');
				
				$scraper_url = $this->core_url;
				$scraper_url .= '?purchase_code='.$this->get_purchase_code();
				$scraper_url .= '&hash='.$hash;
				$scraper_url .= '&email='.$admin_email;
				$scraper_url .= '&domain='.$domain;

                //Dynamic HTML
                echo '<iframe id="scraper-visual-editor" class="scraper-visual-editor" src="'.$scraper_url.'"></iframe>';
            }

            public function render_scraper_tasks(){
                $query = $this->get_scheduled_tasks();

                if( !$query ){
                    echo '<div class="notice inline notice-success notice-alt">';
                    echo '<p>There is no scheduled task.</p>';
                    echo '</div>';
                }else{
                    $permalink_structure = get_option('permalink_structure');
                    $view_type = get_option('_scraper_view', 'Expanded View');
                    $expanded_view = $simple_view = '';
                    if($view_type == 'Simple View'){
                        $simple_view = 'current';
                    }else{
                        $expanded_view = 'current';
                    }
                    ?>
                    <script type="text/javascript">
                        var load_view = '<?php echo $view_type; ?>';
                        document.body.classList.add('scraper-' + load_view.replace(/\s+/g, '-'));                        
                    </script>
                    <?php
                    echo '<br><div id="output_area"><span id="loading_icon"><img src="'.plugins_url( '/assets/images/loading.gif?v=' . SCRAPER_PLUGIN_VERSION, __FILE__ ).'"> <b>Processing, please wait...</b></span><ul id="scraper_logs"></ul></div>';
                    echo '<div class="view-switch" style="float: right;">
                        <a href="#" class="view-list ' . $simple_view  .' scraper-view-button" title="Simple View"><span class="screen-reader-text">Simple View</span></a>
                        <a href="#" class="view-excerpt ' . $expanded_view . ' scraper-view-button" title="Expanded View"><span class="screen-reader-text">Expanded View</span></a>
                    </div>';
                    echo '<table class="widefat fixed striped">';
                        echo '<thead>';
                            echo '<th width="230">Task Name</th>';
                            echo '<th>URL</th>';
                            echo '<th>Interval</th>';
                            echo '<th width="220">Last Process</th>';
                            echo '<th>Total Run</th>';
                            echo '<th width="370">Actions</th>';
                        echo '</thead>';
                        foreach ($query['tasks'] as $key => $item) {
                            $data = json_decode($item['data'], true);
                            $url  = ($data['feedURL'] ? $data['feedURL'] : $data['contentURL']);
                            if ($permalink_structure) {
                                $cron_url  = home_url("scraper/{$item['hash']}");
                            } else {
                                $cron_url  = add_query_arg('scraper_task_id', $item['hash'], home_url());
                            }
                            $full_url = $url;
                            $url  = mb_strimwidth($url, 0, 30, "...");
                            echo '<tr>';
                                echo '<td><a href="admin.php?page=scraper_create&hash='.$item['hash'].'">'.$item['name'].'</a>
                                        <br><small class="simple-view-hide">'.ucwords($item['post_type']).'</small>
                                        <br>
                                        <br><small>Task ID : <b>'.($item['hash']).'</b></small>
                                        <br><a href="javascript:void()" class="toggle-cron-url"><small>Cron URL</a></span> <input type="text" class="scraper-cron-url-box" value="'.$cron_url.'" readonly onClick="this.select();">
                                    </td>';
                                echo '<td><a target="_blank" href="'.$full_url.'">'.$url.'</a><br>
                                        <br><small class="simple-view-hide">Current Page : <b>'.($item['current_page']).'</b></small>
                                        <br><small class="simple-view-hide">Current Index : <b>'.(@$item['last_index'] ? $item['last_index'] : '0').'</b></small>
                                        </td>';
								echo '<td>';
									echo '<p>'.$this->intervals[$item['run_interval']].'</p>';
									if ($item['run_interval'] != '0') {
										if (0 == $item['active']) {
											echo '<p class="simple-view-hide">Next : <b title="'.__('Click on `Start Task` to Schedule').'">'.__('Unscheduled').'</b></p>';
										} else {
											echo '<p class="simple-view-hide">Next : <b>'.$this->next_time_string($item).'</b></p>';
										}
									}
									if($this->time_duration($item['last_complete'], $item['last_run'])){
										echo '<p class="simple-view-hide"><a href="#wpscraper-admin-wrapper" onclick="scraper.showLogs(\''.$item['hash'].'\')">Show Last Log</a></p>';
									}
								echo '</td>';
                                echo '<td>
                                    <p>Last Run : <b>'.$this->time_elapsed_string($item['last_run']).'</b></p>
                                    <p class="simple-view-hide">Last Complete : <b>'.$this->time_elapsed_string($item['last_complete']).'</b></p>
                                    <p class="simple-view-hide">Last Process Duration : <b>'.$this->time_duration($item['last_complete'], $item['last_run']).' seconds</b></p>
                                </td>';
                                echo '<td><p class="simple-view-hide">Completed : <b>'.$item['total_run'].'</b></p>';
                                echo '<p class="simple-view-hide">Processed URL : <b>'.$item['count_run'].'</b></p>';
                                echo '<p class="expanded-view-hide">'. $item['count_run'] . '/' . $item['total_run'] .' Completed</p>';
                                echo '</td>';
                                
                                echo '<td>';

                                //Controls

                                if($item['active'] == 1){
                                    echo '<table class="widefat fixed striped" cellpadding="0" cellspacing="0" border="0">';
                                    echo '<tr>';
                                    echo '<td>';

                                        echo '<table cellpadding="0" cellspacing="0" border="0">';
                                        echo '<tr>';
                                        echo '<td>';
                                            echo '<button onclick="scraper.stop(\''.$item['hash'].'\', this)" class="button button-default scraper-control-button scraper-control-stop"><span class="dashicons dashicons-controls-pause"></span> Pause Task</button>';
                                        echo '</td>';

                                        echo '<td>';
                                            echo '<button onclick="scraper.trigger(\''.$item['hash'].'\', this)" class="button button-default scraper-control-button"><span class="dashicons dashicons-controls-play"></span> Run </button>';
                                        echo '</td>';
                                        echo '</tr>';
                                        echo '</table>';

                                    echo '</td>';
                                    echo '</tr>';

                                    echo '</table>';
                                }else{
                                    echo '<table class="main-tb-click widefat fixed striped" cellpadding="0" cellspacing="0" border="0">';
                                    echo '<tr>';
                                    echo '<td>';

                                        echo '<table cellpadding="0" cellspacing="0" border="0">';
                                        echo '<tr>';
                                        echo '<td>';
                                            if($item['run_interval'] != '0'){                                         
                                                echo '<button style="margin-bottom: 10px; margin-right: 10px;" onclick="scraper.start(\''.$item['hash'].'\', this)" class="button button-primary scraper-control-button scraper-control-start"><span class="dashicons dashicons-update"></span> Start Task</button>';
                                            }
                                            echo '<button style="margin-bottom: 10px; margin-right: 10px;" onclick="scraper.trigger(\''.$item['hash'].'\', this)" class="button button-default scraper-control-button" title="Run Once"><span class="dashicons dashicons-controls-play"></span>Run</button>';
                                            echo '<button onclick="scraper.export(\''.$item['hash'].'\', this)" class="button button-default scraper-control-button" title="Export to CSV"><span class="dashicons dashicons-media-spreadsheet"></span>Export CSV</button>';
                                        echo '</td>';
                                        echo '</tr>';
                                        echo '</table>';

                                    echo '</td>';
                                    echo '</tr>';

                                    echo '<tr>';
                                    echo '<td>';

                                        echo '<table cellpadding="0" cellspacing="0" border="0">';
                                        echo '<tr>';
                                        echo '<td>';
                                            echo '<a href="admin.php?page=scraper_create&hash='.$item['hash'].'" class="button button-small"><span class="dashicons dashicons-edit"></span> Update</a>';
                                        echo '</td>';
                                        echo '<td class="simple-view-hide">';
                                            echo '<button onclick="scraper.clone(\''.$item['hash'].'\', this)" class="button button-small"><span class="dashicons dashicons-admin-page"></span> Clone</button>';
                                        echo '</td>';
                                        echo '<td class="simple-view-hide">';
                                            echo '<button onclick="scraper.reset(\''.$item['hash'].'\', this)" class="button button-small"><span class="dashicons dashicons-image-rotate"></span> Reset</button>';
                                        echo '</td>';
                                        echo '<td>';
                                            echo '<button onclick="scraper.delete(\''.$item['hash'].'\', this)" class="button button-small"><span class="dashicons dashicons-trash"></span> Delete</button>';
                                        echo '</td>';
                                        echo '</tr>';
                                        echo '</table>';
                                    
                                    echo '</td>';
                                    echo '</tr>';

                                    echo '</table>';
                                }

                                echo '</td>';
                            echo '</tr>';
                        }
                    echo '</table>';
                }
            }

            public function render_scraper_license(){
                echo '<form method="POST" action="admin.php?page=scraper_license">';                

                echo '<table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">Purchase Code</th>
                            <td>';
                            $value = $this->with_default('purchase_code', false, $this->options);
                            $this->create_textbox('SCRAPER_purchase_code', $value, '', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'text');
                        echo '</td>
                        </tr>

                        <tr><td colspan="2"><hr></td></tr>

                        <tr>
                            <th scope="row">Bitly API Login</th>
                            <td>';
                            $value = $this->with_default('bitly_login', false, $this->options);
                            $this->create_textbox('SCRAPER_bitly_login', $value, '<a target="_blank" href="https://dev.bitly.com/">Click here to get Bitly login</a>', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'text');
                        echo '</td>
                        </tr>
                        <tr>
                            <th scope="row">Bitly API Key</th>
                            <td>';
                            $value = $this->with_default('bitly_key', false, $this->options);
                            $this->create_textbox('SCRAPER_bitly_key', $value, '', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'password');
                        echo '</td>
                        </tr>

                        <tr><td colspan="2"><hr></td></tr>

                        <tr>
                            <th scope="row">Google Translate API Token</th>
                            <td>';
                            $value = $this->with_default('google_translate', false, $this->options);
                            $this->create_textbox('SCRAPER_google_translate', $value, '<a target="_blank" href="https://cloud.google.com/translate/">Click here to get Google Translate API token</a>', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'password');
                        echo '</td>
                        </tr>
                        <tr>
                            <th scope="row">Yandex Translate API Token</th>
                            <td>';
                            $value = $this->with_default('yandex_translate', false, $this->options);
                            $this->create_textbox('SCRAPER_yandex_translate', $value, '<a target="_blank" href="https://tech.yandex.com/translate/">Click here to get Yandex Translate API token</a>', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'password');
                        echo '</td>
                        </tr>
                        <tr>
                            <th scope="row">DeepL Translate API Token</th>
                            <td>';
                            $value = $this->with_default('deepl_translate', false, $this->options);
                            $this->create_textbox('SCRAPER_deepl_translate', $value, '<a target="_blank" href="https://www.deepl.com/en/api.html">Click here to get DeepL Translate API token</a>', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'password');
                        echo '</td>
                        </tr>

                        <tr><td colspan="2"><hr></td></tr>

                        <tr>
                            <th scope="row">WordAi Email</th>
                            <td>';
                            $value = $this->with_default('wordai_email', false, $this->options);
                            $this->create_textbox('SCRAPER_wordai_email', $value, '', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'text');
                        echo '</td>
                        </tr>
                        <tr>
                            <th scope="row">WordAi Password</th>
                            <td>';
                            $value = $this->with_default('wordai_password', false, $this->options);
                            $this->create_textbox('SCRAPER_wordai_password', $value, '', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'password');
                        echo '</td>
                        </tr>

                        <tr><td colspan="2"><hr></td></tr>

                        <tr>
                            <th scope="row">Enable Error Reporting</th>
                            <td>';
                            $value = $this->with_default('enable_errors', false, $this->options);
                            $this->create_textbox('SCRAPER_enable_errors', $value, '', '', 'checkbox');
                        echo '</td>
                        </tr>

                        <tr>
                            <th scope="row">Disable Memory Limit</th>
                            <td>';
                            $value = $this->with_default('disable_memory_limit', false, $this->options);
                            $this->create_textbox('SCRAPER_disable_memory_limit', $value, '', '', 'checkbox');
                        echo '</td>
                        </tr>
                        <tr>
                            <th scope="row">cURL enabled</th>
                            <td>';
                            echo $this->_isCurl() ? 'Yes' : 'No';
                        echo '</td>
                        </tr>

                    </tbody>
                    </table>';

                echo '<input type="submit" name="save" id="save" class="button button-primary" value="Save Changes">';
                
                echo '</form>';
            }

            /**
             * Uninstall the plugin
             */
            public static function uninstall(){
                delete_option('SCRAPER_settings');
            }

            public function with_default($value, $default, $options){
                if(isset($options['SCRAPER_'.$value])) {
                    return $options['SCRAPER_'.$value];
                }else{
                    return $default;
                }
            }

            public static function setCache($request, $data){
                if (!isset($data) || empty($data))
                    return;
                set_transient('scraper_' . $request, json_encode($data), 0);
            }

            public static function getCache($request){
                return get_transient('scraper_' . $request);
            }

            //Utils
            public function strposa($haystack, $needles=array(), $offset=0) {
                $chr = array();

                foreach($needles as $needle) {
                    if($needle){
                        $res = strpos($haystack, $needle, $offset);

                        if ($res !== false)
                            $chr[$needle] = $res;
                    }
                }

                if (empty($chr))
                    return false;

                return min($chr);
            }

        }

    }

function SCRAPER_Plugin_init() {
	if (class_exists('SCRAPER_Plugin')) {
		$SCRAPER = new SCRAPER_Plugin();

		/**
		 * Add the settings link to the plugins page
		 */
		function SCRAPER_plugin_settings_link($links) {
			$plugin_settings_link = '<a href="admin.php?page=scraper_tasks">Settings</a>';
			$settings_link = '<a href="admin.php?page=scraper_license">License</a>';
			array_unshift($links, $settings_link);
			array_unshift($links, $plugin_settings_link);
			return $links;
		}

		add_filter("plugin_action_links_" . plugin_basename(SCRAPER_PLUGIN_MAIN_FILE_PATH), 'SCRAPER_plugin_settings_link');
	}
}
