<?php

namespace WPEMEventRegistration\eventId;

use VisualComposer\Framework\Container;
use VisualComposer\Framework\Illuminate\Support\Module;
use VisualComposer\Helpers\Traits\EventsFilters;

class Event_Id_Autocomplete_Controller extends Container implements Module
{
    use EventsFilters;

    public function __construct()
    {
        if (!defined('VCV_WPEM_AUTOCOMPLETE_EVENT_ID')) {
            $this->addFilter('vcv:autocomplete:eventId:render', 'event_id_autocomplete_suggester');
            define('VCV_WPEM_AUTOCOMPLETE_EVENT_ID', true);
        }
    }
    /**
     * This function is return autocomplete id of event listing
     * @param $payload, $response
     *
     * @return array
     * @since 3.1.8
     */
    protected function event_id_autocomplete_suggester($response, $payload)
    {
        global $wpdb;
        $searchValue = $payload['searchValue'];
        $returnValue = $payload['returnValue'];
        $eventId1 = (int)$searchValue;
        $searchValue = trim($searchValue);
        $postMetaInfos = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID AS id 
						FROM {$wpdb->posts} 
						WHERE ID LIKE '%d%' ",
                $eventId1 > 0 ? $eventId1 : -1,
                stripslashes($searchValue),
                stripslashes($searchValue)
            ),
            ARRAY_A
        );

        $response['results'] = [];
        if (is_array($postMetaInfos) && !empty($postMetaInfos)) {
            foreach ($postMetaInfos as $value) {
                $data = [];
                $data['value'] = $returnValue ? $value[$returnValue] : $value['id'];
                $data['label'] = $value['id'];
                $response['results'][] = $data;
            }
        }

        return $response;
    }
}