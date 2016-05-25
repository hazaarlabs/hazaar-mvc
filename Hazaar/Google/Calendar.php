<?php

namespace Hazaar\Google;

class Calendar {

    const OAUTH_SCOPE = 'https://www.googleapis.com/auth/calendar';

    private $auth;

    function __construct(OAuth $auth) {

        $this->auth = $auth;

    }

    public function getCalendarList() {

        $client = new Hazaar_Http_Client('https://www.googleapis.com/calendar/v3/users/me/calendarList');

        $client->setHeader('Authorization', 'Bearer ' . $this->auth->getToken());

        $result = \Hazaar\Json::fromString($client->request());

        $calendars = array();

        foreach($result->items as $item) {

            $calendars[$item['id']] = $item;

        }

        return $calendars;

    }

    public function insert($id) {

        $client = new Hazaar_Http_Client('https://www.googleapis.com/calendar/v3/users/me/calendarList');

        $client->setHeader('Authorization', 'Bearer ' . $this->auth->getToken());

        $client->setPost();

        $json = new Hazaar_Json();

        $json->id = $id;

        $client->setJson($json);

        $result = \Hazaar\Json::fromString($client->request(null, true));

        return $result;

    }

    public function update($id, $values) {

        $client = new Hazaar_Http_Client('https://www.googleapis.com/calendar/v3/users/me/calendarList/' . $id);

        $client->setHeader('Authorization', 'Bearer ' . $this->auth->getToken());

        $client->setMethod($client::PUT);

        $json = new Hazaar_Json($values);

        $json->id = $id;

        $client->setJson($json);

        $result = \Hazaar\Json::fromString($client->request(null, true));

        return $result;

    }

}
