The classes located within form the backbone of an engine that creates MIDI data streams.  These streams can either be output via a web application (by adding an HTTP header and using the audio/midi MIME type) or can be output to file.

Their main purpose is to create binary data in the MIDI format.  The spec is well documented, so a main class exists (CoreMidiOutputHelper) to take an array of MIDI event data and output the actual binary data using PHP's pack() function.

In order to create a specific musical part expressed as a series of MIDI events, there is CoreMidiPartHelper.  This class has a private list of events that can be initialized, appended to, and finalized into a complete set that can ultimately be sent to methods in CoreMidiOutputHelper to obtain the binary MIDI data for that part.

Subclassed from this are two additional classes: CoreMidiClickHelper which provides a constant sidestick on the beat for the length of the musical example, and CoreBasicsPartHelper, which has methods to insert harmonic events such as chords, and melodic events such as scales or arpeggios.

Finally, there is an api layer that sits above this (ApiSoundHelper) that allows for the lookup of, say, a specific chord or scale from a database or cache.  It can then transform these models into pitch data that the CoreBasicsPartHelper class understands, and receive the binary data in return.
