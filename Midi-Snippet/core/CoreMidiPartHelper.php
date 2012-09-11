<?php
/**
 * Core helper class for creating midi event arrays of musical instrument parts.
 * 
 * @author Christopher Sung <cs@wholenote.com>
 */
class CoreMidiPartHelper
{	
	protected $events = array(); # holds current set of multi-dimensional midi event codes
	protected $track_ix = 0; # what internal track are we adding events to
	protected $track_total = 1; # how many tracks are we handling?
	protected $loopStart = array(0); # if looping, at what index in event layer does it start
	protected $loopEnd = array(-1); # if looping, at what index in event layer does it end
	protected $channels = array(array(0)); # foreach track, what channel #s are being used?
	
	protected $timeSig = 4; # numerator of time signature, which means only 3/4, 4/4, 5/4, etc.
	protected $ticksPerBeat = 192; # midi ticks per beat
	protected $beatsTotal = 4; # computed by calling layer, how many beats in output?
	protected $countoff = 0; # user pref: 0=none; 1=beginning; 2=always
	protected $loop = 0; # user pref: how many times to loop the output
	
	protected $tempo = 120; # user pref: what tempo to use
	protected $patch = 24; # user pref: what patch to use
	protected $mVol = 120; # master volume
	protected $gtrChan = 8;
	protected $bassChan = 7;
	protected $drumChan = 9;
	
	protected $mStart = 1;
	protected $mEnd = 0;
	
	protected $absMidiTime = 0; # Total # of ticks used
	protected $baseOffsetTicks = 0; # Offset to put track ahead or behind the groove
	protected $leaveRoomTicks = 32;
	
	protected $swing = 50; # swing percentage
	protected $swingAdjustTicks = 0; # Offset to possibly swing eighth notes
	protected $fillOutGroove = false;
	protected $currentStep = 0; # which index in chord progression
	
	protected $initRestTicks = 64;
	
	/**
	 * Create an instance of the class.
	 * 
	 * Common keys in $config come from children: CoreGrooveStyleHelper and CoreSequencePartHelper.
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
	}
	
	/**
	 * Create initial midi events for this part.
	 * 
	 * Initialize the event array, add in base offset, and countoff if present, for all tracks.
	 * 
	 * @param void
	 * @return void
	 */
	public function init()
	{
		# initialize event array
		$this->events = array();
		
		if (0 && $this->initRestTicks) {
			# turn off all channels
			foreach(range(0,15) as $chan) {
				$this->addEvent(0,176+$chan,123,0);
				$this->addEvent(0,176+$chan,120,0);
				$this->addEvent(0,176+$chan,7,0);
			}
			# for all channels in this track
			for($ix=0; $ix<$this->track_total; $ix++) {
				$this->track_ix = $ix;
				$this->addEvent($this->initRestTicks,176+$this->channels[$this->track_ix][0],7,0);
				$this->addEvent($this->initRestTicks,176+$this->channels[$this->track_ix][0],7,$this->mVol);
			}
		}
		if (1 && $this->initRestTicks) {
			# Add a brief rest and then all notes off and all sound off to relevant channels here
			for($ix=0; $ix<$this->track_total; $ix++) {
				$this->track_ix = $ix;
				
				# for all channels in this track
				foreach($this->channels[$this->track_ix] as $chan) {
					$this->addEvent(0,176+$chan,123,0);
					# $this->addEvent(0,176+$chan,120,0);
					$this->addEvent(0,176+$chan,7,0);
				}
				$this->addEvent($this->initRestTicks,176+$this->channels[$this->track_ix][0],7,0);
				$this->addEvent($this->initRestTicks,176+$this->channels[$this->track_ix][0],7,$this->mVol);
			}
		}
		
		# compute initial offset
		$timeOffset = $this->baseOffsetTicks;
		if ($this->countoff>0) $timeOffset += $this->timeSig*$this->ticksPerBeat - $this->leaveRoomTicks;
		
		# compute any swing
		$this->swingAdjustTicks = intval(floor((($this->swing-50)/100.0)*$this->ticksPerBeat));
		
		# init all tracks
		for($ix=0; $ix<$this->track_total; $ix++) {
			$this->track_ix = $ix;
			
			# add extra time to the first chan at beginning
			$chan = $this->channels[$this->track_ix][0];
			$this->addEvent($timeOffset,176+$chan,7,$this->mVol);
			
			# record where this track starts for looping purposes
			$this->loopStart[$this->track_ix] = count($this->events[$this->track_ix]);
			
			# init all channels with current vol, 0 pitch bend, patch
			foreach($this->channels[$this->track_ix] as $chan) {
				$this->addEvent(0,176+$chan,7,$this->mVol);
				$this->addEvent(0,224+$chan,64,64);
				if ($chan!=$this->drumChan) $this->addEvent(0,192+$chan,$this->patch,-1);
			}
		}
		
		# Keep track of where we are
		$this->absMidiTime += $timeOffset;
	}
	
	
	/**
	 * Create finishing midi events for this part.
	 * 
	 * Add in any looping for all tracks.
	 * 
	 * @param void
	 * @return void
	 */
	public function finish()
	{
		# TODO: are we missing some sustain at the end?
		#  add any needed extra time to one single chan at end
		$timeOffset = 0;
		if ($this->countoff==0) $timeOffset += $this->leaveRoomTicks;
		
		# var_dump($timeOffset); var_dump($this->absMidiTime); exit;
		
		for($ix=0; $ix<$this->track_total; $ix++) {
			$this->track_ix = $ix;
			$this->addEvent($timeOffset,176+$this->channels[$this->track_ix][0],7,$this->mVol);
			# print "$timeOffset,".(176+$this->channels[$this->track_ix][0]).",7,".$this->mVol."\n";
		}
		
		# TODO: this addresses groove parts -- will this fail for sequences?
		for($j=5; $j>=0; $j--) {
			if ($this->oldPitches[$j]>=0) {
				$this->addEvent(0,144+$this->channelMap[$j],$this->baseNote[$j]+$this->oldPitches[$j],0);
				$this->oldPitches[$j] = -1;
				# print "0,".(144+$this->channelMap[$j]).",".($this->baseNote[$j]+$this->oldPitches[$j]).",0\n";
			}
		}
		
		# Check if we need to fill out for lessons with grooves
		if ($this->fillOutGroove) {
			$fillMeasures = $this->mEnd-floor($this->currentStep/$this->res);
			if ($fillMeasures>0) {
				$timeOffset = $fillMeasures*$this->timeSig*$this->ticksPerBeat;
				for($ix=0; $ix<$this->track_total; $ix++) {
					$this->track_ix = $ix;
					$this->addEvent($timeOffset,176+$this->channels[$this->track_ix][0],7,$this->mVol);
				}
			}
		}
	
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
		
		# Add a brief rest and then all notes off and all sound off to relevant channels here
		$this->addEvent(2*$this->ticksPerBeat,176+$this->channels[$this->track_ix][0],7,$this->mVol);
		for($ix=0; $ix<$this->track_total; $ix++) {
			$this->track_ix = $ix;
			
			# for all channels in this track
			foreach($this->channels[$this->track_ix] as $chan) {
				$this->addEvent(0,176+$chan,123,0);
				$this->addEvent(0,176+$chan,120,0);
			}
		}
	}
	
	/**
	 * Exposes the private event list.
	 * 
	 * @param void
	 * @return array (2-dimensional)
	 */
	public function getEvents()
	{
		return $this->events;
	}
	
	/**
	 * Add an event to the private midi event list for this track.
	 * 
	 * NOTE: public -- let any layer write into it for now.
	 * 
	 * @param $t0 int (time offset in ticks)
	 * @param $evt1 int (midi event byte #1)
	 * @param $evt2 int (midi event byte #2)
	 * @param $evt3 int (midi event byte #3)
	 * @return void
	 */
	public function addEvent($t0,$evt1,$evt2,$evt3)
	{
		list($t1,$t2) = array(0,0);
		if ($t0>0) list($t1,$t2) = $this->computeMidiTime($t0);
		
		# print "$t1,$t2,$evt1,$evt2,$evt3\n"; sleep(1);
		
		if ($t1>0) $this->events[$this->track_ix][] = $t1 ;	# Add first byte if offset is large enough
		$this->events[$this->track_ix][] = $t2;
		$this->events[$this->track_ix][] = $evt1;
		$this->events[$this->track_ix][] = $evt2;
		if ($evt3>=0) $this->events[$this->track_ix][] = $evt3;
	}
	
	/**
	 * Convert input tick value to midi timing bytes.
	 * 
	 * @param $tOffset int (time offset in ticks)
	 * @return array ($time1,$time2)
	 */
	protected function computeMidiTime($tOffset)
	{ 
		$time1 = intval(floor($tOffset/128));
		if ($time1>0) $time1 = $time1 + 128;
		$time2 = $tOffset%128;
		
		return array($time1,$time2);
	}
	
}
