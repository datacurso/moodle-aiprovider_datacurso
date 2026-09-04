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

namespace aiprovider_datacurso;

use core_privacy\local\metadata\collection;
use core_privacy\tests\provider_testcase;
use aiprovider_datacurso\privacy\provider;

/**
 * Privacy provider tests for Datacurso AI provider.
 *
 * The plugin no longer stores personal data in local tables (the rate limit is enforced and
 * accumulated by the external Datacurso service), so it only declares an external-location link.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2025 Wilber Narvaez <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversMethod(\aiprovider_datacurso\privacy\provider::class, 'get_metadata')]
#[\PHPUnit\Framework\Attributes\CoversMethod(\aiprovider_datacurso\privacy\provider::class, 'get_contexts_for_userid')]
final class privacy_provider_test extends provider_testcase {
    /**
     * Test setup.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Metadata only declares the external location (no local tables).
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('aiprovider_datacurso'));
        $this->assertInstanceOf(collection::class, $collection);
        $this->assertNotEmpty($collection->get_collection());
    }

    /**
     * With no local data, no user context is returned.
     */
    public function test_get_contexts_for_userid_is_empty(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertCount(0, provider::get_contexts_for_userid($user->id));
    }
}
