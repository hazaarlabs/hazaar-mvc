<?php

namespace Hazaar\Events;

/**
 * Interface Queuable
 *
 * Marks an event listener as queuable. Queuable listeners will have their
 * handle method executed later when the event queue is processed, rather
 * than immediately upon event dispatch.
 */
interface Queuable {}
