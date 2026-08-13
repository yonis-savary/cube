<?php

namespace Cube\Tests\Units\Core\Classes;

/**
 * Does not use the Component trait again on purpose : a subclass must still get
 * its own instance slot rather than writing into the one its parent reads from.
 */
class SpecializedCounter extends Counter {}
