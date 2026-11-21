# Build as an Assignment submission plugin, not a standalone activity

The Journal is graded once as a whole, which matches how Moodle Assignment already treats a submission. So we build `assignsubmission_reflectivejournal` instead of a standalone `mod_` activity. This gives us grading, gradebook, rubrics, marking workflow, extensions, group and blind marking for free. The cost is that anything which works per Entry (Comments, revealing the next Entry) has to be built inside the submission plugin's own UI and tables, because Assignment only knows about one submission per student.
