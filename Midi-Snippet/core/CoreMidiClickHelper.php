<?php
/**
 * Core helper class for creating a single midi click track.
 * 
 * @author Christopher Sung <cs@wholenote.com>
 */
class CoreMidiClickHelper extends CoreMidiPartHelper
{	
	/**
	 * Create an instance of the class and then creates all midi events.
	 * 
	 * Required keys in $config: countoff (int), timeSig (int), ticksPerBeat (int), beatsTotal (int).
	 * 
	 * @param array $config
	 * @return object
	 */
	public function __construct($config=null)
	{
		# Initialize any vals
		if (is_array($config)) {
			foreach(get_class_vars(__CLASS__) as $name=>$value) {
				if (isset($config[$name])) {
					$this->$name = $config[$name];
				}
			}
		}
		
		# Create the data stream
		$this->init();
		$this->create();
		$this->finish();
	}
	
	/**
	 * Create initial midi events for this part.
	 * 
	 * Initialize the event array, add the countoff if present
	 * 
	 * @param void
	 * @return void
	 */
	public function init()
	{ 
		# Return if no countoff is needed
		if ($this->countoff<1) return;
		
		# initialize event array
		$this->events = array();
		
		if ($this->initRestTicks) {
			# Add a brief rest and then all notes off and all sound off to relevant channels here
			for($ix=0; $ix<$this->track_total; $ix++) {
				$this->track_ix = $ix;
				
				# for all channels in this track
				foreach($this->channels[$this->track_ix] as $chan) {
					$this->addEvent(0,176+$chan,123,0);
					$this->addEvent(0,176+$chan,7,0);
				}
				$this->addEvent($this->initRestTicks,176+$this->channels[$this->track_ix][0],7,0);
				$this->addEvent($this->initRestTicks,176+$this->channels[$this->track_ix][0],7,$this->mVol);
			}
		}
		
		# Put in the initial countoff
		for($i=0; $i<$this->timeSig; $i++) { 
			$vol = ($i==0) ? 127 : 70;
			$this->addEvent(0,153,37,$vol);
			$this->addEvent($this->ticksPerBeat,153,37,0);
		}
		
		# record where this track starts for looping purposes
		$this->loopStart[$this->track_ix] = count($this->events[$this->track_ix]);
	}
	
	/**
	 * Create click for the indicated # of beats.
	 * 
	 * @param void
	 * @return void
	 */
	public function create()
	{ 
		# Return if just the countoff is needed
		if ($this->countoff<2) return;
		
		# Add a note on and note off for each beat in the click track
		for($i=0; $i<$this->beatsTotal; $i++) { 
			$vol = (($i%$this->timeSig)==0) ? 127 : 70;
			$this->addEvent(0,153,37,$vol);
			$this->addEvent($this->ticksPerBeat,153,37,0);
		}
	}
	
	/**
	 * Add in any looping.
	 * 
	 * @param void
	 * @return void
	 */
	public function finish()
	{	
		for($ix=0; $ix<$this->track_total; $ix++) {
			$this->track_ix = $ix;
			$this->loopEnd[$this->track_ix] = count($this->events[$this->track_ix]);
		}
		
		# Create loop of previously created data here
		for($z=0;$z<$this->loop;$z++) {
			for($ix=0; $ix<$this->track_total; $ix++) {
				$this->track_ix = $ix;
				for($j=$this->loopStart[$this->track_ix];$j<$this->loopEnd[$this->track_ix];$j++) {
					$this->events[$this->track_ix][] = $this->events[$this->track_ix][$j];
				}
			}
		}
	}
}
