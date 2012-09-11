<?php
/**
 * Core helper class for creating midi output data.
 * 
 * @author Christopher Sung <cs@wholenote.com>
 */
class CoreMidiOutputHelper
{	
	private $output; # holds all binary packed data
	
	
	# required upon construction
	private $ticksPerBeat = 192; # midi ticks per beat
	private $ntracks = 2; # computed by calling layer, how many output tracks?
	private $tempo = 120; # user pref: what tempo to use
	
	/**
	 * Create an instance of the class.
	 * 
	 * Required keys in $config: ticksPerBeat (int), ntracks (int), tempo (int).
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
		
		# Create the initial data stream
		$this->addMidiHeader();
		$this->addTempoTrack();
	}
	
	/**
	 * DEPRECATED: Initialize binary midi data for output.
	 * 
	 * @param void
	 * @return void
	 */
	private function init()
	{
		$this->addMidiHeader();
		$this->addTempoTrack();
	}
	
	/**
	 * Turns a midi event list into binary packed data and returns to caller.
	 * 
	 * @param array $events
	 * @return binary string
	 */
	public function createDataFromEvents($events)
	{ 
		unset($data);
		
		$count = count($events);
		
		# compute total bytes for the track
		$sBytes = $count + 4; $sBytes1 = floor($sBytes/256); $sBytes2 = $sBytes%256;
	
		# track hdr
		$data .= pack("c8",
			hexdec("4d"),hexdec("54"),hexdec("72"),hexdec("6B"),hexdec("00"),hexdec("00"),$sBytes1,$sBytes2);
		
		# track data
		$packed = "";
		foreach ($events as $event) $packed = $packed.pack("c",$event);
		$data .= $packed;
		
		# track end
		$data .= pack("c4",hexdec("00"),hexdec("ff"),hexdec("2f"),hexdec("00"));
		
		return $data;
	}
	
	/**
	 * Turns a midi event list into binary packed data and appends to private string.
	 * 
	 * @param array $events
	 * @return binary string
	 */
	public function addTrackFromEvents($events)
	{ 
		$count = count($events);
		
		# compute total bytes for the track
		$sBytes = $count + 4; $sBytes1 = floor($sBytes/256); $sBytes2 = $sBytes%256;
	
		# track hdr
		$this->output .= pack("c8",
			hexdec("4d"),hexdec("54"),hexdec("72"),hexdec("6B"),hexdec("00"),hexdec("00"),$sBytes1,$sBytes2);
		
		# track data
		$packed = "";
		foreach ($events as $event) $packed = $packed.pack("c",$event);
		$this->output .= $packed;
		
		# track end
		$this->output .= pack("c4",hexdec("00"),hexdec("ff"),hexdec("2f"),hexdec("00"));
	}
	
	/**
	 * Initialize binary midi data stream and append to private string.
	 * 
	 * @param void
	 * @return void
	 */
	public function addMidiHeader()
	{ 
		# Print file header data
		$this->output .= pack("c14",
			hexdec("4d"),hexdec("54"),hexdec("68"),hexdec("64"),hexdec("00"),hexdec("00"),hexdec("00"),hexdec("06"),
			hexdec("00"),hexdec("01"),hexdec("00"),$this->ntracks,hexdec("00"),$this->ticksPerBeat);
	}
	
	/**
	 * Add tempo track to binary midi data stream and append to private string.
	 * 
	 * @param void
	 * @return void
	 */
	public function addTempoTrack()
	{ 
		# Compute tempo bytes
		$tmpTempo = floor(60000000/$this->tempo); # Total usec in a minute / beats per minute
		$tByte1 = floor($tmpTempo/65536);
		$tByte2 = floor(($tmpTempo - ($tByte1*65536))/256);
		$tByte3 = $tmpTempo - ($tByte1*65536) - ($tByte2*256);

		# Print tempo track data
		$this->output .= pack("c28",
			hexdec("4d"),hexdec("54"),hexdec("72"),hexdec("6B"),hexdec("00"),hexdec("00"),hexdec("00"),hexdec("14"),
			hexdec("00"),hexdec("ff"),hexdec("58"),hexdec("04"),hexdec("04"),hexdec("02"),hexdec("18"),hexdec("08"),
			hexdec("00"),hexdec("ff"),hexdec("51"),hexdec("03"),$tByte1,$tByte2,$tByte3,hexdec("84"),
			hexdec("00"),hexdec("ff"),hexdec("2f"),hexdec("00"));
	}
	
	/**
	 * Exposes the private binary packed string.
	 * 
	 * @param void
	 * @return binary string
	 */
	public function getOutput()
	{
		return $this->output;
	}
	
}
