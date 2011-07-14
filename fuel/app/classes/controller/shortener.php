<?php
/**
 * Created by JetBrains PhpStorm.
 * User: mjsilva
 * Date: 11-07-2011
 * Time: 20:07
 * To change this template use File | Settings | File Templates.
 */

class Controller_Shortener extends Controller_Template {

	public function action_index()
	{
		$this->action_set_url();
	}

	public function action_set_url()
	{

		$user_id = Cookie::get('user_id');

		if ( empty($user_id) )
		{
			$user_id = uniqid();
			Cookie::set('user_id', $user_id, 60 * 60 * 24 * 360 * 10);
		}

		$val = \Validation::factory();

		$val->add('url', 'URL')->add_rule("required");

		if ( $val->run() )
		{
			$short_url = $this->_get_short_url();

			$db_data = array(
				"short_url" => $short_url,
				"real_url" => Input::post('url'),
				"creator_ip_address" => Input::real_ip(),
				"date_created" => Date::factory()->format("mysql"),
				"user_id" => $user_id
			);

			Model_Url::set_url($db_data);

			$short_url = Config::get('base_url') . $short_url;

			if ( Input::is_ajax() )
			{
				$response = array(
					"short_link" => $short_url,
					"stats_link" => $short_url . "/stats",
					"long_link" => Input::post('url')
				);

				exit(json_encode($response));
			}
		}

		$view = View::factory('form');
		$view->set("validation", $val, false);

		$user_urls = Model_Url::get_short_urls(array("user_id" => $user_id));
		$view->set("user_urls", $user_urls, false);


		$this->template->set("title", "Shrink your huge URL");
		$this->template->set("content", $view, false);
	}

	private function _get_short_url()
	{
		$last_short_url = Model_Url::get_last_short_url();
		$return = ($last_short_url === NULL) ? ShortUrl::next("") : ShortUrl::next($last_short_url);
		return $return;
	}

	public function action_get_url($url)
	{
		$url = Model_Url::get_url($url);

		if ( !$url ) Request::show_404();

		$db_data = array(
			"short_url" => $url["short_url"],
			"ip_address" => Input::real_ip(),
			"date_created" => Date::factory()->format("mysql")
		);

		Model_Url::set_url_hit($db_data);

		Response::Redirect($url["real_url"]);
	}

	public function action_stats($url)
	{
		$url_db = Model_Url::get_url($url);

		if ( !$url_db ) Request::show_404();

		$url_hits = Model_Url::get_url_hits($url);

		$view = View::factory('stats');

		$chart_data = array();

		foreach ( $url_hits as $hist )
		{
			$ts = strtotime($hist["date_created"]);

			if ( empty($ts) ) throw new exception("Couldn't parse date: {$hist["date_created"]}");

			$month = Date::factory($ts)->format("%Y-%m");

			if ( !array_key_exists($month, $chart_data) )
			{
				$chart_data[$month] = 1;
			}
			else
			{
				$chart_data[$month] += 1;
			}
		}


		$view->set("chart_data", $chart_data, false);
		$view->set("short_url", Config::get("base_url") . $url);
		$view->set("real_url", $url_db["real_url"]);

		$this->template->set("title", "Shrink your huge URL");
		$this->template->set("content", $view, false);

		$this->response->body = $view;
	}

	/**
	 * The 404 action for the application.
	 *
	 * @access  public
	 * @return  void
	 */
	public function action_404()
	{
		$messages = array('Aw, crap!', 'Bloody Hell!', 'Uh Oh!', 'Nope, not here.', 'Huh?');
		$data['title'] = $messages[array_rand($messages)];

		// Set a HTTP 404 output header
		$this->response->status = 404;
		$this->template->set("title", "Shrink your huge URL");
		$this->template->set("content", View::factory('404', $data), false);
	}

}