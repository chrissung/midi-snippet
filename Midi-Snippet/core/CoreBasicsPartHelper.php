<?php
/**
 * Core helper class for playback of stringed instrument basics (notes, chords, scales).
 * 
 * @author Christopher Sung <cs@wholenote.com>
 */
class CoreBasicsPartHelper extends CoreMidiPartHelper
{	
	protected $baseNote = array(); # midi number of strings 1-6
	protected $baseOffsetTicks = 0; # Offset to put the part ahead or behind the beat
	protected $leaveRoomTicks = 0;
	
	/**
	 * Create an instance of the class.
	 * 
	 * Required keys in $config:
	 * 'baseNote'=>array(64,59,55,50,45,40), # midi number of open strings 1 to n
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
		
		# call parent init() method
		parent::init();
	}
	
	public function finishAndGetEvents()
	{
		# call parent finish() method
		parent::finish();
		
		return $this->events;
	}
	
	/**
	 * Convenience function to create a C cadence to begin an ear training test.
	 * 
	 * @param void
	 * @return void
	 */
	public function insertIntro()
	{
		$this->insertChord(2,array(64,60,55,52,48,-1));
		$this->insertChord(1,array(65,60,57,53,48,41));
		$this->insertChord(1,array(67,62,55,50,47,43));
		$this->insertChord(3,array(64,60,55,52,48,-1));
		$this->addEvent($this->ticksPerBeat,176,7,$this->mVol);
	}
	
	/**
	 * Add a chord of midi numbers to the stream using a chord array and number of beats.
	 * 
	 * @param $beats (int)
	 * @param $chord (array of midi numbers)
	 * @return void
	 */
	public function insertChord($beats,$chord)
	{
		$tTime = $beats*$this->ticksPerBeat;
		
		# Create note-ons
		$tOffset = 0;
		for($i=0;$i<count($chord);$i++) {
			if ($chord[$i]<0) continue;
			$offset = mt_rand(0,10);
			$this->addEvent($offset,144,$chord[$i],90+mt_rand(0,30));
			$tOffset += $offset;
		}
		
		# Create note-offs
		for($i=0;$i<count($chord);$i++) {
			if ($chord[$i]<0) continue;
			$offset = (!$i) ? $tTime - $tOffset-5 : 0;
			$this->addEvent($offset,144,$chord[$i],0);
		}
		$this->addEvent(5,176,7,$this->mVol);
	}
	
	/**
	 * Add a chord of string pitches to the stream using a string-fret array and number of beats.
	 * 
	 * Index of pitch array correlates with $baseNote property.
	 * 
	 * @param $pitches (array of fret numbers, high string to low)
	 * @return void
	 */
	public function insertChordPitches($pitches,$absolute=false)
	{	
		$abspitches = array();
		if ($absolute) { # using abs midi pitches
			$abspitches = $pitches;
		}
		else {
			$baseNote = $this->baseNote;
			for($j=5; $j>=0; $j--) { # using fret and string matrix relative to baseNote array
				if ($pitches[$j]<0) $abspitches[$j] = $pitches[$j];
				else $abspitches[$j] = $baseNote[$j]+$pitches[$j];
			}
		}
		
		$start_ix = count($abspitches) - 1;
		
		# Write note ons for the chord
		$absMidiTime = 0;
		for($j=$start_ix; $j>=0; $j--) {
			if (!is_numeric($abspitches[$j]) || $abspitches[$j]<0) continue;
			$lTime = 5+mt_rand(0,10);
			$this->addEvent($lTime,144,$abspitches[$j],90+mt_rand(0,30));
			$absMidiTime += $lTime;
		}
		
		# Insert the hold
		$hold = 3*$this->ticksPerBeat-$absMidiTime;
		$this->addEvent($hold,176,7,$this->mVol);
		
		$absMidiTime = 3*$this->ticksPerBeat;
		
		# Write note offs
		for($j=$start_ix; $j>=0; $j--) {
			if (!is_numeric($abspitches[$j]) || $abspitches[$j]<0) continue;
			$lTime = 5+mt_rand(0,10);
			$this->addEvent($lTime,144,$abspitches[$j],0);
			$absMidiTime += $lTime;
		}
		$hold = 4*$this->ticksPerBeat-$absMidiTime;
		$this->addEvent($hold,176,7,$this->mVol);
	}
	
	/**
	 * Add an arpeggiated chord of string pitches to the stream using a string-fret array and number of beats.
	 * 
	 * Index of pitch array correlates with $baseNote property.
	 * 
	 * @param $pitches (array of fret numbers, high string to low)
	 * @return void
	 */
	public function insertChordArpPitches($pitches,$absolute=false)
	{	
		$abspitches = array();
		if ($absolute) { # using abs midi pitches
			$abspitches = $pitches;
		}
		else {
			$baseNote = $this->baseNote;
			for($j=5; $j>=0; $j--) { # using fret and string matrix relative to baseNote array
				if ($pitches[$j]<0) $abspitches[$j] = $pitches[$j];
				else $abspitches[$j] = $baseNote[$j]+$pitches[$j];
			}
		}
		
		$start_ix = count($abspitches) - 1;
		
		# Write note ons for the chord
		$absMidiTime = 0;
		for($j=$start_ix,$notes_played=0; $j>=0; $j--) {
			if (!is_numeric($abspitches[$j]) || $abspitches[$j]<0) continue;
			$lTime = 5+mt_rand(0,10);
			$this->addEvent($lTime,144,$abspitches[$j],90+mt_rand(0,30));
			$absMidiTime += $lTime;
			
			# Let a beat pass after this attack
			$lTime = ++$notes_played*$this->ticksPerBeat-$absMidiTime;
			$absMidiTime = $notes_played*$this->ticksPerBeat;
			$this->addEvent($lTime,176,7,$this->mVol);
		}
		
		# Insert the hold until the end of the 7th beat
		$hold = 7*$this->ticksPerBeat-$absMidiTime;
		$this->addEvent($hold,176,7,$this->mVol);
		
		$absMidiTime = 7*$this->ticksPerBeat;
		
		# Write note offs
		for($j=$start_ix; $j>=0; $j--) {
			if (!is_numeric($abspitches[$j]) || $abspitches[$j]<0) continue;
			$lTime = 5+mt_rand(0,10);
			$this->addEvent($lTime,144,$abspitches[$j],0);
			$absMidiTime += $lTime;
		}
		
		# Insert silence until the end of the 8th beat
		$hold = 8*$this->ticksPerBeat-$absMidiTime;
		$this->addEvent($hold,176,7,$this->mVol);
		
		$absMidiTime = 8*$this->ticksPerBeat;
		
		for($j=$start_ix; $j>=0; $j--) {
			if (!is_numeric($abspitches[$j]) || $abspitches[$j]<0) continue;
			$lTime = 5+mt_rand(0,10);
			$this->addEvent($lTime,144,$abspitches[$j],90+mt_rand(0,30));
			$absMidiTime += $lTime;
		}
		
		# Insert the hold
		$hold = 15*$this->ticksPerBeat-$absMidiTime;
		$this->addEvent($hold,176,7,$this->mVol);
		
		$absMidiTime = 15*$this->ticksPerBeat;
		
		# Write note offs
		for($j=$start_ix; $j>=0; $j--) {
			if (!is_numeric($abspitches[$j]) || $abspitches[$j]<0) continue;
			$lTime = 5+mt_rand(0,10);
			$this->addEvent($lTime,144,$abspitches[$j],0);
			$absMidiTime += $lTime;
		}
		$hold = 16*$this->ticksPerBeat-$absMidiTime;
		$this->addEvent($hold,176,7,$this->mVol);
	}
	
	
	/**
	 * Add a list of midi numbers to the stream using a pitch array.
	 * 
	 * @param $pitches (array of midi numbers)
	 * @param $remNotes (int) number of rest notes to insert at end
	 * @return void
	 */
	public function insertScalePitches($pitches,$remNotes)
	{	
		$absMidiTime = 0;
		for($i=0; $i<count($pitches); $i++) {
			# Note On
			$absMidiTime = $i*($this->ticksPerBeat/2);
			$lTime = mt_rand(0,10);
			
			# Simulate alternate pick
			if (!($i%2)) $this->addEvent($lTime,144,$pitches[$i],70+floor(mt_rand(0,15)));
			else $this->addEvent($lTime,144,$pitches[$i],100+floor(mt_rand(0,20)));
			$absMidiTime += $lTime;
			
			# Note off
			$lTime = mt_rand(0,10);
			$lOffset = ($this->ticksPerBeat/2)-10-$lTime;
			$this->addEvent($lOffset,144,$pitches[$i],0);
			
			# Left over time
			$leftOverTime = ($i+1)*($this->ticksPerBeat/2) - ($absMidiTime + ($this->ticksPerBeat/2)-10-$lTime);
			$this->addEvent($leftOverTime,176,7,$this->mVol);
		}
		
		# Fill in remaining beats in last measure
		$leftOverTime = $remNotes*($this->ticksPerBeat/2);
		$this->addEvent($leftOverTime,176,7,$this->mVol);
	}
	
}
