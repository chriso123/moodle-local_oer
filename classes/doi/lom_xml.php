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

/**
 * Class lom_xml
 *
 * Extract the necessary data (DOI and identifier) from the XML webservice response.
 * The response is in LOM (Learning object metadata) format.
 */
class lom_xml {
    /**
     * Extract the data.
     *
     * @param string $xmlstring XML string from webservice.
     * @return \stdClass
     * @throws \Exception
     */
    public static function extract(string $xmlstring): \stdClass {
        $result = new \stdClass();
        try {
            $xml = new SimpleXMLElement($xmlstring, LIBXML_NOCDATA);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        $result->doi = self::extract_dois($xml);
        $result->resumptionToken = self::extract_resumption_token($xml);
        return $result;
    }

    /**
     * Extract DOIs from xml.
     *
     * @param \SimpleXMLElement $xml
     * @return array
     */
    private static function extract_dois(\SimpleXMLElement $xml): array {
        $xml->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
        $xml->registerXPathNamespace('lom', 'https://oer-repo.uibk.ac.at/lom');

        $result = [];
        $records = $xml->xpath('//oai:record');

        foreach ($records as $record) {
            // The . (dot) at the start means the search is relative.
            // TODO: the moodle identifier will be changed as soon as the oer identifier is available in webservice.
            $moodlenodes = $record->xpath('.//lom:identifier[lom:catalog="moodle"]/lom:entry/lom:langstring');
            $doinodes = $record->xpath('.//lom:identifier[lom:catalog="DOI"]/lom:entry/lom:langstring');

            if (!empty($moodlenodes) && !empty($doinodes)) {
                $identifier = trim((string) $moodlenodes[0]);
                $doi = trim((string) $doi);

                // Only add if both have been found.
                if ($identifier !== '' && $doi !== '') {
                    $result[$identifier] = $doi;
                }
            }
        }

        return $result;
    }

    /**
     * Extract resumption token.
     *
     * Resumption token is used to load next page. If empty, no more pages are available.
     *
     * @param \SimpleXMLElement $xml
     * @return string
     */
    private static function extract_resumption_token(\SimpleXMLElement $xml): string {
        $token = $xml->xpath('//oai:resumptionToken');
        if (!empty($token)) {
            return (string) $token[0];
        }
        return '';
    }
}
