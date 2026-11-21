<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace assignsubmission_reflectivejournal\local;

/**
 * The teacher's settings for one Reflective journal Assignment.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class settings {
    /** @var int Default maximum number of Entries. */
    public const DEFAULT_MAX_ENTRIES = 6;

    /** @var int Highest allowed maximum number of Entries. */
    public const MAX_ENTRIES_LIMIT = 52;

    /** @var string Journal Submission happens at the deadline only. */
    public const MODE_DEADLINE = 'deadline';

    /** @var string The student may also submit early with the Assignment's submit button. */
    public const MODE_BUTTON = 'button';

    /** @var string[] Every Reflective Model, in display order. */
    public const MODELS = ['gibbs', 'driscoll', 'kolb', 'none'];

    /**
     * Constructor.
     *
     * @param int $maxentries Maximum number of Entries in a Journal.
     * @param string[] $allowedmodels Reflective Models students may choose.
     * @param string $submissionmode One of the MODE_ constants.
     */
    public function __construct(
        /** @var int Maximum number of Entries in a Journal. */
        public readonly int $maxentries = self::DEFAULT_MAX_ENTRIES,
        /** @var string[] Reflective Models students may choose. */
        public readonly array $allowedmodels = self::MODELS,
        /** @var string One of the MODE_ constants. */
        public readonly string $submissionmode = self::MODE_DEADLINE,
    ) {
    }

    /**
     * Read the settings stored for an Assignment.
     *
     * @param \assign_submission_plugin $plugin The plugin instance for the Assignment.
     * @return self
     */
    public static function from_plugin(\assign_submission_plugin $plugin): self {
        $max = $plugin->get_config('maxentries');
        $models = $plugin->get_config('models');
        $mode = $plugin->get_config('submissionmode');
        return new self(
            $max === false ? self::DEFAULT_MAX_ENTRIES : (int) $max,
            $models === false || $models === '' ? self::MODELS : self::clean_models(explode(',', $models)),
            $mode === self::MODE_BUTTON ? self::MODE_BUTTON : self::MODE_DEADLINE,
        );
    }

    /**
     * Keep only known models, in display order.
     *
     * @param array $models Model names.
     * @return string[]
     */
    public static function clean_models(array $models): array {
        return array_values(array_intersect(self::MODELS, $models));
    }

    /**
     * Whether a model may be chosen for a new Entry.
     *
     * @param string $model Model name.
     * @return bool
     */
    public function allows_model(string $model): bool {
        return in_array($model, $this->allowedmodels, true);
    }

    /**
     * Whether the student may submit the whole Journal before the deadline.
     *
     * @return bool
     */
    public function allows_early_submission(): bool {
        return $this->submissionmode === self::MODE_BUTTON;
    }
}
