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

namespace local_oer\task;

use local_oer\doi\oai_pmh;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/clilib.php');

/**
 * Class update_doi_task
 */
class update_doi_task extends scheduled_task {
    /**
     * Get name function
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:updatedoi', 'local_oer');
    }

    /**
     * Execute function
     *
     * @return void
     */
    public function execute(): void {
        // Before doing anything, lets find out if there is something to do.
        if (!get_config('local_oer', 'add_doi_link')) {
            return;
        }

        // TODO: check time to run.
        // Maybe load the release time config and run it in that schedule.

        global $DB, $USER;
        $snapshots = $DB->get_records('local_oer_snapshot', ['doi' => null]);
        if (empty($snapshots)) {
            return; // No snapshots with empty doi found.
        }
        $missing = [];
        foreach ($snapshots as $snapshot) {
            $missing[$snapshot->identifier] = $snapshot;
        }

        $webservice = new oai_pmh();
        $token = null;

        do {
            try {
                $data = $webservice->fetch_records($token);
            } catch (\Exception $e) {
                mtrace("Error: " . $e->getMessage());
                break;
            }

            if (!$data || empty($data->doi)) {
                if (!$data) {
                    break;
                } // Stop if end reached.
                // If just empty DOIs but valid token, continue to next page.
                $token = $data->resumptionToken;
                continue;
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                foreach ($data->doi as $identifier => $doi) {
                    if (isset($missing[$identifier])) {
                        $record = new \stdClass();
                        $record->id = $missing[$identifier];
                        $record->doi = $doi;
                        $record->usermodified = $USER->id;
                        $record->timemodified = time();
                        $DB->update_record('local_oer_snapshot', $record);
                        unset($missing[$identifier]);
                    }
                }

                $transaction->allow_commit();

                mtrace("Batch saved.");
            } catch (\Exception $e) {
                // If DB fails, rollback this batch but try to continue or log.
                $transaction->rollback($e);
                mtrace("Batch failed: " . $e->getMessage());
            }

            if (empty($missing)) {
                break; // No more snapshots to update.
            }

            $token = $data->resumptionToken;
        } while (!empty($token));
    }
}
