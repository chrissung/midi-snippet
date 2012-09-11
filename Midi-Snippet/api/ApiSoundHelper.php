<?php

/**
 * Helper class for sound-based api data definitions and methods.
 * 
 * TODO: document
 * 
 * Note: these defs are guitar-centric, though they needn't be
 */
class ApiSoundHelper
{
	# DREAMING: inherent input checking for all params
	private function rules()
	{
		return array(
			'not_numeric'=>array(
				'data'=>true,
				'baseNote'=>true,
			),
			'not_editable'=>array(
				'ticksPerBeat'=>true,
				'beatsTotal'=>true,
				'ntracks'=>true,
				'loopStart'=>true,
				'loopEnd'=>true,
				'baseNote'=>true,
			),
		);
	}
	
	
	private function paramsGet($input) {
		
		# $_GET params to internal property map
		$map = array(
			'i'=>'id',
			'p'=>'page',
			'countoff'=>'countoff',
			'loop'=>'loop',
			't'=>'tempo',
			'tempo'=>'tempo',
			'patch'=>'patch',
			'gv'=>'gvol',
			'bv'=>'bvol',
			'dv'=>'dvol',
			'ms'=>'mStart',
			'me'=>'mEnd',
			'co'=>'countoff',
			'click'=>'countoff',
			'f'=>'style',
			'style'=>'style',
			'r'=>'res',
			'res'=>'res',
			'd'=>'data',
			'data'=>'data',
			'u1'=>'useSeq1',
			'u2'=>'useSeq2',
			'u3'=>'useGrv',
		);
		
		$params = array(
			'lessonId'=>0,
			'page'=>0,
			'timeSig'=>4, # numerator of time signature, which means only 3/4, 4/4, 5/4, etc.
			'ticksPerBeat'=>192, # midi ticks per beat
			'beatsTotal'=>4, # computed by calling layer, how many beats in output?
			'ntracks'=>2, # computed by calling layer, how many output tracks?
			'loopStart'=>array(0), # if looping, at what index in event layer does it start
			'loopEnd'=>array(-1), # if looping, at what index in event layer does it end
			'countoff'=>0, # user pref: 0=none, 1=beginning, 2=always
			'loop'=>0, # user pref: how many times to loop the output
			'tempo'=>120, # user pref: what tempo to use
			'patch'=>24, # user pref: what patch to use
			'mVol'=>120, # master volume
			'baseNote'=>array(64,59,55,50,45,40), # base midi pitches for each string
		
			/* groove/sequence specific */
			'seqNum'=>1,
			'mStart'=>1,
			'mEnd'=>-1,
			'data'=>'',
			'style'=>1,
			'res'=>1,
			'gvol'=>100,
			'bvol'=>100,
			'dvol'=>100,
		
			/* lesson specific */
			'useSeq1'=>1,
			'useSeq2'=>1,
			'useGrv'=>1,
		
			/* accompaniment specific */
			'seq1'=>1,
			'seq2'=>1,
			'grv1'=>1,
		);
		$rules = $this->rules();
		foreach($input as $name=>$value) {
			# Remap key if not an exact match
			if (isset($map[$name])) $name = $map[$name];
			if (!isset($params[$name])
			|| $rules['not_editable'][$name]
			|| (!$rules['not_numeric'][$name] && !is_numeric($value))) continue;
			
			$params[$name] = $value;
		}
		
		return $params;
	}
	
	public function getPatchMap()
	{	
		return array(
24=>'Ac Nylon',
25=>'Ac Steel',
26=>'Elec Jazz',
27=>'Elec Clean',
29=>'Overdriven',
30=>'Distortion',
32=>'Ac Bass',
33=>'Bass Fing',
34=>'Bass Pick',
35=>'Fretless',
36=>'Slap 1',
37=>'Slap 2',
104=>'Sitar',
105=>'Banjo',
		);
	}
	
	
	# Create a metronome click track
	public function getmetro($tempo,$beats=null)
	{	
		# Construct our midi object based upon prefs
		$params['tempo'] = $tempo;
		$params['ntracks'] = 2;
		$helper_c_midiout = new CoreMidiOutputHelper($params);
		
		# Construct our midi object based upon prefs
		$params['countoff'] = 2;
		$params['timeSig'] = 4;
		$params['beatsTotal'] = is_numeric($beats) ? $beats : 512;
		
		# Add in countoff/click track, internally ignored if countoff=0
		$helper_c_click = new CoreMidiClickHelper($params);
		$events = $helper_c_click->getEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_click);
		
		# Return the accumulated midi output data
		return $helper_c_midiout->getOutput();
	}
	
	
	# Play one repeated tuning note
	public function gettuning($pitch,$input)
	{	
		# Resolve default params with caller prefs
		$params = $this->paramsGet($input);
		
		$params['countoff'] = $params['loop'] = 0; # no loop or countoff with this functionality
		
		# Construct our midi object based upon prefs
		$helper_c_midiout = new CoreMidiOutputHelper($params);
		
		$helper_c_basics = new CoreBasicsPartHelper($params);
		
		/*
		 * Begin custom data here
		 */
		for($i=0; $i<16; $i++) { 
			$helper_c_basics->addEvent(0,144,$pitch,127); # Note On
			$helper_c_basics->addEvent(6*$params['ticksPerBeat'],144,$pitch,0); # Note off after 6 beats
		}
		/*
		 * End custom data
		 */
		
		$events = $helper_c_basics->finishAndGetEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_basics);
		
		# Return the accumulated midi output data
		return $helper_c_midiout->getOutput();
	}
	
	
	# Play any collection of pitches with or w/o C-F-G-C cadence intro
	public function getpitches($pitches,$intro,$input)
	{	
		# Resolve default params with caller prefs
		$params = $this->paramsGet($input);
		
		$params['countoff'] = 0; # no loop or countoff with this functionality
		
		# Compute the number of measures, beats, and total notes to play back
		$beatsTotal = 3;
		if ($intro) $beatsTotal +=2;
		$params['beatsTotal'] = $beatsTotal;
		
		# Construct our midi object based upon prefs
		$helper_c_midiout = new CoreMidiOutputHelper($params);
		
		# Add in countoff/click track, internally ignored if countoff=0
		$helper_c_click = new CoreMidiClickHelper($params);
		$events = $helper_c_click->getEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_click);
		
		$helper_c_basics = new CoreBasicsPartHelper($params);
		
		/*
		 * Begin custom data here
		 */
		if ($intro) $helper_c_basics->insertIntro();
		
		# Hardcode to 3 times total for now
		for($i=0; $i<3; $i++)
		{ # Note On
			$absMidiTime = 0;
			for($j=0; $j<count($pitches); $j++) {
				if ($pitches[$j]<=0) continue;
				$lTime = 5+mt_rand(0,10);
				$helper_c_basics->addEvent($lTime,144+$j,$pitches[$j],90+mt_rand(0,30));
				$absMidiTime += $lTime;
			}
			
			# Insert the hold
			$helper_c_basics->addEvent(3*$params['ticksPerBeat']-$absMidiTime,176,7,$params['mVol']);
			$absMidiTime = 3*$params['ticksPerBeat'];
			
			# Write note offs
			for($j=0; $j<count($pitches); $j++) {
				if ($pitches[$j]<=0) continue;
				$lTime = 5+mt_rand(0,10);
				$helper_c_basics->addEvent($lTime,144+$j,$pitches[$j],0);
				$absMidiTime += $lTime;
			}
			
			# Fill out the measure
			$helper_c_basics->addEvent(4*$params['ticksPerBeat']-$absMidiTime,176,7,$params['mVol']);
		}
		/*
		 * End custom data
		 */
		
		$events = $helper_c_basics->finishAndGetEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_basics);
		
		# Return the accumulated midi output data
		return $helper_c_midiout->getOutput();
	}
	
	
	public function getchord($id,$input)
	{	
		$type = 'basics-chord';
		$master = ApiGenHelper::get(array(
			'type'=>$type,'data'=>array('id'=>$id),
		));
		$notedata = is_array($master['data']) ? $master['data'] : array();
		if (!is_array($notedata) || !count($notedata)) return;
		
		# Create pitch array
		$pitches = array(-1,-1,-1,-1,-1,-1);
		foreach($notedata as $item) {
			$pitches[$item['string']-1] = $item['fret'];
		}
		
		# Resolve default params with caller prefs
		$params = $this->paramsGet($input);
		
		# Compute the number of beats to play back
		$params['beatsTotal'] = $params['timeSig'];
		
		# Construct our midi object based upon prefs
		$helper_c_midiout = new CoreMidiOutputHelper($params);
		
		# Add in countoff/click track, internally ignored if countoff=0
		$helper_c_click = new CoreMidiClickHelper($params);
		$events = $helper_c_click->getEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_click);
		
		$params['leaveRoomTicks'] = 32; # HACK: needed to put strum on the beat
		$helper_c_basics = new CoreBasicsPartHelper($params);
		$helper_c_basics->insertChordPitches($pitches);
		$events = $helper_c_basics->finishAndGetEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_basics);
		
		# Return the accumulated midi output data
		return $helper_c_midiout->getOutput();
	}
	
	
	public function getchordarppitches($pitchtext,$input)
	{	
		return $this->getchordpitches($pitchtext,$input,true); # last param means 'arpeggiated'
	}
	
	
	public function getchordpitches($pitchtext,$input,$arpeggiated=false,$absolute=false)
	{	
		$delim = strstr($pitchtext,":") ? ':' : '~';
		$pitches = explode($delim,$pitchtext);
		
		if (!is_array($pitches) || !count($pitches)) return;
		
		# Resolve default params with caller prefs
		$params = $this->paramsGet($input);
		
		# Compute the number of beats to play back
		$params['ntracks'] = count($pitches)+1;
		$params['beatsTotal'] = $params['timeSig'];
		
		# Construct our midi object based upon prefs
		$helper_c_midiout = new CoreMidiOutputHelper($params);
		
		# Add in countoff/click track, internally ignored if countoff=0
		$helper_c_click = new CoreMidiClickHelper($params);
		$events = $helper_c_click->getEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_click);
		
		$params['leaveRoomTicks'] = 32; # HACK: needed to put strum on the beat
		$helper_c_basics = new CoreBasicsPartHelper($params);
		if ($arpeggiated) {
			$helper_c_basics->insertChordArpPitches($pitches,$absolute);
		}
		else {
			$helper_c_basics->insertChordPitches($pitches,$absolute);
		}
		
		$events = $helper_c_basics->finishAndGetEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_basics);
		
		# Return the accumulated midi output data
		return $helper_c_midiout->getOutput();
	}
	
	
	public function getscale($id,$input)
	{	
		$type = 'basics-scale';
		$master = ApiGenHelper::get(array(
			'type'=>$type,'data'=>array('id'=>$id),
		));
		$notedata = is_array($master['data']) ? $master['data'] : array();
		if (!is_array($notedata) || !count($notedata)) return;
		
		# Resolve default params with caller prefs
		$params = $this->paramsGet($input);
		
		# Add descending
		for($i=count($notedata)-2; $i>=0; $i--) $notedata[] = $notedata[$i];
		
		# Compute the number of measures, beats, and total notes to play back
		$nNotes = count($notedata);
		$nMeasures = ceil($nNotes/$params['timeSig']);
		$tNotes = $nNotes;
		if ($nNotes%2) $tNotes++;
		$params['beatsTotal'] = floor($tNotes/2);
		
		# Get pitches from notedata
		$pitches = array();
		for($i=0; $i<count($notedata); $i++) $pitches[] = $notedata[$i]['midi_number'];
		
		# Construct our midi object based upon prefs
		$helper_c_midiout = new CoreMidiOutputHelper($params);
		
		# Add in countoff/click track, internally ignored if countoff=0
		$helper_c_click = new CoreMidiClickHelper($params);
		$events = $helper_c_click->getEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_click);
		
		$helper_c_basics = new CoreBasicsPartHelper($params);
		$helper_c_basics->insertScalePitches($pitches,$tNotes-$nNotes);
		$events = $helper_c_basics->finishAndGetEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_basics);
		
		# Return the accumulated midi output data
		return $helper_c_midiout->getOutput();
	}
	
	
	public function getscalepitches($pitchtext,$input)
	{	
		$delim = strstr($pitchtext,":") ? ':' : '~';
		$pitches = explode($delim,$pitchtext);
		
		if (!is_array($pitches) || !count($pitches)) return;
		
		# Resolve default params with caller prefs
		$params = $this->paramsGet($input);
		
		# Compute the number of measures, beats, and total notes to play back
		$nNotes = count($pitches);
		$nMeasures = ceil($nNotes/$params['timeSig']);
		$tNotes = $nNotes;
		if ($nNotes%2) $tNotes++;
		$params['beatsTotal'] = floor($tNotes/2);
		
		# Construct our midi object based upon prefs
		$helper_c_midiout = new CoreMidiOutputHelper($params);
		
		# Add in countoff/click track, internally ignored if countoff=0
		$helper_c_click = new CoreMidiClickHelper($params);
		$events = $helper_c_click->getEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_click);
		
		$helper_c_basics = new CoreBasicsPartHelper($params);
		$helper_c_basics->insertScalePitches($pitches,$tNotes-$nNotes);
		$events = $helper_c_basics->finishAndGetEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_basics);
		
		# Return the accumulated midi output data
		return $helper_c_midiout->getOutput();
	}
	
	
	public function getarp($id,$input)
	{	
		$type = 'basics-arp';
		$master = ApiGenHelper::get(array(
			'type'=>$type,'data'=>array('id'=>$id),
		));
		$notedata = is_array($master['data']) ? $master['data'] : array();
		if (!is_array($notedata) || !count($notedata)) return;
		
		# Resolve default params with caller prefs
		$params = $this->paramsGet($input);
		
		# Add descending
		for($i=count($notedata)-2; $i>=0; $i--) $notedata[] = $notedata[$i];
		
		# Compute the number of measures, beats, and total notes to play back
		$nNotes = count($notedata);
		$nMeasures = ceil($nNotes/$params['timeSig']);
		$tNotes = $nNotes;
		if ($nNotes%2) $tNotes++;
		$params['beatsTotal'] = floor($tNotes/2);
		
		# Get pitches from notedata
		$pitches = array();
		for($i=0; $i<count($notedata); $i++) $pitches[] = $notedata[$i]['midi_number'];
		
		# Construct our midi object based upon prefs
		$helper_c_midiout = new CoreMidiOutputHelper($params);
		
		# Add in countoff/click track, internally ignored if countoff=0
		$helper_c_click = new CoreMidiClickHelper($params);
		$events = $helper_c_click->getEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_click);
		
		$helper_c_basics = new CoreBasicsPartHelper($params);
		$helper_c_basics->insertScalePitches($pitches,$tNotes-$nNotes);
		$events = $helper_c_basics->finishAndGetEvents();
		foreach($events as $event) {
			$helper_c_midiout->addTrackFromEvents($event);
		}
		unset($helper_c_basics);
		
		# Return the accumulated midi output data
		return $helper_c_midiout->getOutput();
	}
	
}
