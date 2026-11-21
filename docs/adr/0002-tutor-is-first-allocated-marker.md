# The Tutor is the student's first allocated marker

Each student has exactly one Tutor, who is notified when Entries lock and who writes the Comments. We don't add our own tutor-mapping. Instead we reuse Assignment's marker allocation, so cover works as it does in Moodle: editing teachers and managers (`mod/assign:manageallocations`) can see and Comment on any Journal, and they hand cover over by reallocating the marker. If no marker is allocated, or marking allocation is off, anyone who can grade acts as the Tutor.

## Consequences

Moodle 4.5 stores one `allocatedmarker` per student in `assign_user_flags`. Moodle 5.3 stores possibly several in `assign_allocated_marker`. We pick the first in allocation order. Looking up "who is this student's Tutor" therefore has to be one function with a version-specific branch, and other allocated markers in 5.3 are deliberately not treated as the Tutor.
