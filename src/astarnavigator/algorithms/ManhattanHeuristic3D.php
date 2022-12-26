<?php

class ManhattanHeuristic3D implements IDistanceAlgorithm
{
	public function calculate(PathTile $from, PathTile $to)
	{
		if($from instanceof PathTileXYZ && $to instanceof PathTileXYZ){
			return Utils::manh_distance($from->asVector(), $to->asVector());
		}
		return 666;
	}

}

