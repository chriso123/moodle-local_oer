<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Open Educational Resources Plugin
 *
 * @package    local_oer
 * @author     Christian Ortner <christian.ortner@tugraz.at>
 * @copyright  2022 Educational Technologies, Graz, University of Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oer\doi;

use core\url;

/**
 * Class oai_pmh
 *
 * Call the webservice of the repository and load the first or next page of the LOM dataset.
 */
class oai_pmh {
    /**
     * Server url of the OAI-PMH service.
     *
     * When parameters are attached, they will be replaced with the necessary parameters for the call.
     *
     * @var string
     */
    private string $serverurl;

    /**
     * Constructor.
     *
     * Fetch server url from config.
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function __construct() {
        $this->serverurl = get_config('local_oer', 'oai_pmh_url');

        if (empty($this->serverurl)) {
            throw new \moodle_exception('local_oer/oai_pmh_url not set', 'local_oer');
        }
    }

    /**
     * Fetch the records of one page from the OAI-PMH service.
     *
     * @param string|null $resumptiontoken token set in webservice for next page, empty if at end. Null as start value.
     * @return \stdClass|null
     * @throws \core\exception\coding_exception
     * @throws \core\exception\moodle_exception
     * @throws \moodle_exception
     */
    public function fetch_records(?string $resumptiontoken = null): ?\stdClass {
        if ($resumptiontoken === '') {
            return null; // Last page was reached, nothing more to load.
        }

        $params = [
            'verb' => 'ListRecords',
        ];
        if (!empty($resumptiontoken)) {
            $params['resumptionToken'] = $resumptiontoken; // No other params than verb allowed.
        } else {
            $params['metadataPrefix'] = 'lom'; // For initial call.
        }

        $url = new url($this->serverurl, []);
        $url->remove_all_params(); // Remove params if given in setting.
        $url->params($params);

        $curl = new \curl();
        $options = [
            'CURLOPT_TIMEOUT' => 120,
            'CURLOPT_CONNECTTIMEOUT' => 20,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_USERAGENT' => 'Moodle-local-oer-doi-explorer/1.0',
        ];
        $response = $curl->get($url->out(false), $options);

        if ($curl->get_errno() !== 0) {
            throw new \moodle_exception('errorconnection', 'error', $curl->error);
        }

        $info = $curl->get_info();

        if ($info['http_code'] == 503) {
            throw new \moodle_exception('Server is busy, try again later. Http Error: ' . $info['http_code']);
        } else if ($info['http_code'] != 200) {
            throw new \moodle_exception('HTTP Error: ' . $info['http_code']);
        }

        return lom_xml::extract($response);
    }
}
