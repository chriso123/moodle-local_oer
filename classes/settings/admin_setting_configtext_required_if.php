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
 * @copyright  2025 Educational Technologies, Graz, University of Technology
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oer\settings;

/**
 * Class admin_setting_configtext_required_if
 *
 * Extend the configtext to be required if a checkbox is checked.
 */
class admin_setting_configtext_required_if extends \admin_setting_configtext {
    /**
     * Name of the setting, this setting is dependent on.
     *
     * @var string
     */
    protected string $dependentonname;

    /**
     * Constructor.
     *
     * Only PARAM_URL is supported for the configtext element.
     *
     * @param string $name The name of this setting
     * @param string $visiblename Lang string shown to user
     * @param string $description Description of this setting
     * @param mixed $defaultsetting Default value
     * @param string $dependentonname The full name of the CHECKBOX setting (e.g., 'local_oer/checkbox')
     */
    public function __construct(
        string $name,
        string $visiblename,
        string $description,
        string $defaultsetting,
        string $dependentonname,
    ) {
        $this->dependentonname = $dependentonname;
        parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_URL);
    }

    /**
     * Validate.
     *
     * First, validate parent. Params and size will be validated there.
     * Then get the checkbox from the post data, as it is not stored yet.
     * When the checkbox is set,
     *
     * @param $data
     * @return \lang_string|mixed|string|true
     */
    public function validate($data) {
        $result = parent::validate($data);
        if ($result !== true) {
            return $result;
        }

        $checkbox = 's_' . str_replace('/', '_', $this->dependentonname);
        $ischecked = optional_param($checkbox, 0, PARAM_BOOL);

        if ($ischecked && empty($data)) {
            return get_string('required', 'core');
        }

        if (!empty($data)) {
            if (!filter_var($data, FILTER_VALIDATE_URL)) {
                return get_string('invalidurl', 'local_oer');
            }
        }

        return true;
    }
}
