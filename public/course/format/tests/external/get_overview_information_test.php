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

namespace core_courseformat\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use core_external\external_api;
use stdClass;

/**
 * Tests for courseformat get_overview_information web service.
 *
 * @package    core_courseformat
 * @category   test
 * @copyright  2025 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_courseformat\external\get_overview_information
 */
final class get_overview_information_test extends \externallib_advanced_testcase {
    public function test_get_overview_information(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \core\context\course::instance($course->id);

        $clock = $this->mock_clock_with_frozen();
        $end = $clock->time() + DAYSECS;
        $mod1 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'duedate' => $end]);
        $mod2 = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        // This should not be included in the overview table.
        $this->getDataGenerator()->create_module('feedback', ['course' => $course->id]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $modinfo = get_fast_modinfo($course);
        $mod1 = $modinfo->get_cm($mod1->cmid);
        $mod2 = $modinfo->get_cm($mod2->cmid);

        // Get course state.
        $result = get_overview_information::execute($course->id, 'assign');
        $result = external_api::clean_returnvalue(get_overview_information::execute_returns(), $result);

        // Validate the final structure has the values from the overview table export.
        $renderer = \core\di::get(\core\output\renderer_helper::class)->get_core_renderer();

        $overviewtable = new \core_courseformat\output\local\overview\overviewtable($course, 'assign');

        $tabledata = $overviewtable->get_exporter($context)->export($renderer);

        $this->assertEquals($tabledata->courseid, $result['courseid']);
        $this->assertEquals($tabledata->hasintegration, $result['hasintegration']);

        foreach ($tabledata->headers as $header) {
            $resultelement = $this->find_by_attribute($result['headers'], 'key', $header->key);
            $this->assertEquals($header->name, $resultelement['name']);
            $this->assertEquals($header->key, $resultelement['key']);
            $this->assertEquals($header->align, $resultelement['align']);
        }

        // The test should include only the two assign activities.
        $this->assertCount(2, $result['activities']);
        $this->assertEquals(count($tabledata->activities), count($result['activities']));

        // Validate activity 1.
        $activity = $this->find_by_attribute($result['activities'], 'cmid', (int) $mod1->id);
        $activitytable = $this->find_by_attribute($tabledata->activities, 'cmid', $mod1->id);

        $this->assertEquals($mod1->id, $activity['cmid']);
        $this->assertEquals($mod1->context->id, $activity['contextid']);
        $this->assertEquals('assign', $activity['modname']);
        $this->assertEquals($mod1->name, $activity['name']);
        $this->assertEquals($mod1->url->out(false), $activity['url']);

        foreach ($activitytable->items as $item) {
            $resultelement = $this->find_by_attribute($activity['items'], 'key', $item->key);
            $this->assertEquals($item->name, $resultelement['name']);
            $this->assertEquals($item->key, $resultelement['key']);
            $this->assertEquals($item->contenttype, $resultelement['contenttype']);
            $this->assertEquals($item->exportertype, $resultelement['exportertype']);
            $this->assertEquals($item->alertlabel, $resultelement['alertlabel']);
            $this->assertEquals($item->alertcount, $resultelement['alertcount']);
            $this->assertEquals($item->contentjson, $resultelement['contentjson']);
            $this->assertEquals($item->extrajson, $resultelement['extrajson']);
        }

        // Validate activity 2.
        $activity = $this->find_by_attribute($result['activities'], 'cmid', (int) $mod2->id);
        $activitytable = $this->find_by_attribute($tabledata->activities, 'cmid', $mod2->id);

        $this->assertEquals($mod2->id, $activity['cmid']);
        $this->assertEquals($mod2->context->id, $activity['contextid']);
        $this->assertEquals('assign', $activity['modname']);
        $this->assertEquals($mod2->name, $activity['name']);
        $this->assertEquals($mod2->url->out(false), $activity['url']);

        foreach ($activitytable->items as $item) {
            $resultelement = $this->find_by_attribute($activity['items'], 'key', $item->key);
            $this->assertEquals($item->name, $resultelement['name']);
            $this->assertEquals($item->key, $resultelement['key']);
            $this->assertEquals($item->contenttype, $resultelement['contenttype']);
            $this->assertEquals($item->exportertype, $resultelement['exportertype']);
            $this->assertEquals($item->alertlabel, $resultelement['alertlabel']);
            $this->assertEquals($item->alertcount, $resultelement['alertcount']);
            $this->assertEquals($item->contentjson, $resultelement['contentjson']);
            $this->assertEquals($item->extrajson, $resultelement['extrajson']);
        }
    }

    /**
     * Helper function to find an item by its key in an array of items.
     *
     * @param array $items The array of items to search.
     * @param string $attribute The attribute to search by (e.g., 'key').
     * @param string $value The key to search for.
     * @return object|null The found item or null if not found.
     */
    private function find_by_attribute(array $items, string $attribute, string $value): stdClass|array|null {
        foreach ($items as $item) {
            $itemarray = (array) $item; // Ensure we can access properties as array.
            if ($itemarray[$attribute] == $value) {
                return $item;
            }
        }
        return null;
    }
}
