<?php

//include the file for spotify and youtube
include("Spotify.php");
require __DIR__ . "/YoutubeAudioLink.php";


class ACRCloud{
    
    //class attributes
    private $apiResponse;
    private $spotifyData;
    private $youtubeData;
    private $song;
    //declare a variables for spotify and youtube class
    
    
    //default constructor
    public function __construct() {
            $this-> apiResponse = "";
            $this -> spotifyData  = "";
            $this -> youtubeData = "";
            $this -> song = "";
            //instantiate spotify and youtube class
        }
    
    //this function  will get song send from the client
    public function generateSong()
    {
        //if no directory 'recordings' is present it will create it.
        //this is used for debuggin if heroku can create directory dynamically
        if(!is_dir("recordings"))
        {
            //creating dir and changing permissions
    	    $res = mkdir("recordings",0777); 
    	    echo "recordings directory was created<br>";
        }
    	    

        //if there is no error and file is present
        if(isset($_FILES['file']) and !$_FILES['file']['error'])
        {
            //attribute variable is assigned to song.mp3
            $this -> song = "song.mp3";
            
            //move the song.mp3 file to the recordings directory.
            move_uploaded_file($_FILES['file']['tmp_name'], "recordings/" . $this -> song);
            
            //return true meaning succesful
            return true;
        }
        
        //if there was an error display an error message and return false
        else
            return false;   
        
    }
    
    //this function will make the api call to get metadata of song
    public function makeAPICall()
    {
        //prepare bash shell command through shell through php acr functions and song attribute as an argument
        $command = escapeshellcmd("python ACRCloudPy/linux/x86-64/python2.7/test.py recordings/" . $this -> song);
        
        //execute command
        $output = shell_exec($command);
 
        //after execute command remove song from recordings directory to allow multiple requests from different people
        unlink("recordings/" . $this -> song);
        
        //remove new line characters and backlashes from api response to decode into json and split into array format
        $string = str_replace('\n', '', $output);
        $string = str_replace('\"', '', $output);
    
        //convert string into an associative array
        $this -> apiResponse = json_decode($string, true);

        //this array will hold all data for client
        $responseArray["title"] = $this -> apiResponse["metadata"]["music"][0]["title"];
        $responseArray["artists"] = $this -> apiResponse["metadata"]["music"][0]["artists"][0]["name"];//this is an array
        $responseArray["release_date"] = $this -> apiResponse["metadata"]["music"][0]["release_date"];
        $responseArray["album"] = $this -> apiResponse["metadata"]["music"][0]["album"]["name"];
        $responseArray["audio_link"] = "www.google.com";
        
        //Spotify retrieve artist id
        
        //declare class
        $Spotify = new Spotify();
        
        //retrieve token
        $Spotify->retrieveAccessToken();
        
        //get artist id and set it
        $getThisArtist = $this->apiResponse["metadata"]["music"][0]["artists"][0]["name"];
        $artistID = $Spotify->getArtistId( $getThisArtist );
        
        $responseArray["recommendation_list"] = $Spotify->retrieveRecommendedArtists();
        
        $responseArray["genre"] = $this -> apiResponse["metadata"]["music"][0]["genres"];//this is an array
        
        //this array will hold data for spotify and youtube
        $localArray["spotify_artists"] = $this -> apiResponse["metadata"]["music"][0]["spotify"]["artists"];
        $localArray["spotify_track_id"] = $this -> apiResponse["metadata"]["music"][0]["spotify"]["track"]["id"];
        $localArray["youtube_id"] = $this -> apiResponse["metadata"]["music"][0]["youtube"]["vid"];
        
        //use the keys in $localArray values for spotify class and youtube class.
        //the result of each class store them in $responseArray. For spotify soter in $responseArray["recommendation_list"], and for youtube in $responseArray["audio_link"]
        
        $youtube = new YoutubeAudioLink();
        //Setting the path for the youtube-dl library
        $youtube->setBinPath("../bin/");
        
        if(!empty($localArray["youtube_id"])) {
            $yLink = $youtube->getAudioLinkById($localArray["youtube_id"]);
            $responseArray["audio_link"] = $yLink;
        }else{
            $yLink = $youtube->getAudioBySearching($responseArray["title"]);
            $responseArray["audio_link"] = $yLink;
        }
        
        header('Content-Type: application/json');
        echo json_encode($responseArray);

        
    }
    
    //get full response from api
    public function getAPIResponse()
    {
        return $this -> apiResponse;
    }
        
    public function getSpotifyData()
    {
        return $this -> spotifyData;
    }
    
    public function getYoutubeData()
    {
        return $this -> youtubeData;
    }
    
    public function setSpotifyData($name)
    {
        $this -> spotifyData = $name;
    }
    
}

?>