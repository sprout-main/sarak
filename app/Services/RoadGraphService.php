<?php

namespace App\Services;

class RoadGraphService
{
    /**
     * In-memory topological road network graph for Bangued, Abra.
     * Contains verified intersections (nodes) and connecting road segments (edges).
     */
    private array $nodes = [];
    private array $adjacency = [];

    public function __construct()
    {
        $this->loadBanguedRoadNetwork();
    }

    /**
     * Compute optimal road route using A* search on the local road graph.
     *
     * @return array{
     *     distance_meters: float,
     *     coordinates: array<int, array{0: float, 1: float}>,
     *     road_names: array<int, string>,
     *     source: string,
     *     is_fallback: bool
     * }|null
     */
    public function findRoute(float $startLat, float $startLng, float $destLat, float $destLng): ?array
    {
        if (empty($this->nodes)) {
            return null;
        }

        $startNode = $this->findNearestNode($startLat, $startLng);
        $destNode = $this->findNearestNode($destLat, $destLng);

        if (!$startNode || !$destNode) {
            return null;
        }

        // Distance from start pin to nearest start road node
        $entryDist = $this->haversine($startLat, $startLng, $this->nodes[$startNode]['lat'], $this->nodes[$startNode]['lng']);
        // Distance from dest road node to dest pin
        $exitDist = $this->haversine($destLat, $destLng, $this->nodes[$destNode]['lat'], $this->nodes[$destNode]['lng']);

        // A* Pathfinding
        $pathNodes = $this->aStar($startNode, $destNode);

        if (empty($pathNodes)) {
            return null;
        }

        // Reconstruct polyline & road names
        $coordinates = [];
        $coordinates[] = [round($startLng, 6), round($startLat, 6)];

        $totalRoadDistance = 0.0;
        $roadNames = [];

        $pathCount = count($pathNodes);
        for ($i = 0; $i < $pathCount; $i++) {
            $u = $pathNodes[$i];
            $uNode = $this->nodes[$u];

            // If not first, add edge segment coordinates & distance
            if ($i > 0) {
                $prev = $pathNodes[$i - 1];
                $edge = $this->getEdge($prev, $u);

                if ($edge) {
                    $totalRoadDistance += (float) $edge['distance_m'];
                    if (!empty($edge['road_name']) && !in_array($edge['road_name'], $roadNames, true)) {
                        $roadNames[] = $edge['road_name'];
                    }

                    // Add intermediate geometry if defined
                    if (!empty($edge['geometry'])) {
                        foreach ($edge['geometry'] as $geoPt) {
                            $coordinates[] = [round((float) $geoPt[0], 6), round((float) $geoPt[1], 6)];
                        }
                    }
                }
            }

            $coordinates[] = [round((float) $uNode['lng'], 6), round((float) $uNode['lat'], 6)];
        }

        $coordinates[] = [round($destLng, 6), round($destLat, 6)];
        $totalDistance = $entryDist + $totalRoadDistance + $exitDist;

        return [
            'distance_meters' => round($totalDistance, 1),
            'coordinates' => $coordinates,
            'road_names' => $roadNames,
            'source' => 'local_graph',
            'is_fallback' => false,
        ];
    }

    /**
     * A* Search Algorithm
     */
    private function aStar(string $startId, string $goalId): array
    {
        if ($startId === $goalId) {
            return [$startId];
        }

        $destLat = $this->nodes[$goalId]['lat'];
        $destLng = $this->nodes[$goalId]['lng'];

        $openSet = [$startId => true];
        $cameFrom = [];

        $gScore = [$startId => 0.0];
        $fScore = [$startId => $this->haversine($this->nodes[$startId]['lat'], $this->nodes[$startId]['lng'], $destLat, $destLng)];

        while (!empty($openSet)) {
            // Pick node in openSet with lowest fScore
            $current = null;
            $lowestF = INF;
            foreach ($openSet as $nodeId => $_) {
                $f = $fScore[$nodeId] ?? INF;
                if ($f < $lowestF) {
                    $lowestF = $f;
                    $current = $nodeId;
                }
            }

            if ($current === $goalId) {
                // Reconstruct path
                $path = [$current];
                while (isset($cameFrom[$current])) {
                    $current = $cameFrom[$current];
                    array_unshift($path, $current);
                }
                return $path;
            }

            unset($openSet[$current]);
            $currentG = $gScore[$current] ?? INF;

            foreach ($this->adjacency[$current] ?? [] as $neighbor => $edgeData) {
                $tentativeG = $currentG + (float) $edgeData['distance_m'];

                if ($tentativeG < ($gScore[$neighbor] ?? INF)) {
                    $cameFrom[$neighbor] = $current;
                    $gScore[$neighbor] = $tentativeG;
                    $h = $this->haversine($this->nodes[$neighbor]['lat'], $this->nodes[$neighbor]['lng'], $destLat, $destLng);
                    $fScore[$neighbor] = $tentativeG + $h;
                    $openSet[$neighbor] = true;
                }
            }
        }

        return [];
    }

    /**
     * Find nearest graph node to given coordinates.
     */
    public function findNearestNode(float $lat, float $lng): ?string
    {
        $closestId = null;
        $minDist = INF;

        foreach ($this->nodes as $id => $node) {
            $dist = $this->haversine($lat, $lng, $node['lat'], $node['lng']);
            if ($dist < $minDist) {
                $minDist = $dist;
                $closestId = $id;
            }
        }

        return $closestId;
    }

    private function getEdge(string $from, string $to): ?array
    {
        return $this->adjacency[$from][$to] ?? null;
    }

    /**
     * Populate the Bangued municipal road graph.
     */
    private function loadBanguedRoadNetwork(): void
    {
        // Intersections & Major Waypoints across Bangued Poblacion and Corridors
        $nodes = [
            // Calaba / Northern Entrance
            'calaba_bridge'      => ['lat' => 17.6185, 'lng' => 120.6175, 'name' => 'Calaba Bridge / Abra River'],
            'hospital_junction'  => ['lat' => 17.6155, 'lng' => 120.6180, 'name' => 'Abra Provincial Hospital Junction'],
            'calaba_south'       => ['lat' => 17.6105, 'lng' => 120.6186, 'name' => 'Calaba - Zone 1 Boundary'],
            
            // Zone 1 / North Poblacion
            'zone1_north'        => ['lat' => 17.6065, 'lng' => 120.6190, 'name' => 'Zone 1 North (Taft St)'],
            'zone1_taft_burgos'  => ['lat' => 17.6025, 'lng' => 120.6195, 'name' => 'Taft St & Burgos St Intersection'],
            'zone1_east'         => ['lat' => 17.6045, 'lng' => 120.6235, 'name' => 'Zone 1 East (Dangdangla link)'],

            // Zone 2 / Northeast
            'zone2_mckinley'     => ['lat' => 17.6005, 'lng' => 120.6208, 'name' => 'McKinley St & Zone 2'],
            'zone2_east'         => ['lat' => 17.5995, 'lng' => 120.6255, 'name' => 'Zone 2 East / Cassamata Foot'],

            // Zone 3 / Northwest
            'zone3_west'         => ['lat' => 17.6015, 'lng' => 120.6145, 'name' => 'Zone 3 River Road Connector'],
            'zone3_magallanes'   => ['lat' => 17.6010, 'lng' => 120.6172, 'name' => 'Magallanes & Taft St Junction'],

            // Zone 4 / Central Capitol & Cathedral Core
            'plaza_north'        => ['lat' => 17.5975, 'lng' => 120.6185, 'name' => 'Town Plaza North / Provincial Capitol'],
            'capitol_front'      => ['lat' => 17.5970, 'lng' => 120.6170, 'name' => 'Capitol Grounds West'],
            'plaza_south'        => ['lat' => 17.5956, 'lng' => 120.6185, 'name' => 'Town Plaza South / Cathedral'],
            'cathedral_junction' => ['lat' => 17.5958, 'lng' => 120.6198, 'name' => 'Cathedral & Rizal St Intersection'],

            // Zone 5 / Commercial Core & Public Market
            'market_junction'    => ['lat' => 17.5946, 'lng' => 120.6188, 'name' => 'Bangued Public Market Junction'],
            'zone5_taft_south'   => ['lat' => 17.5925, 'lng' => 120.6185, 'name' => 'Taft St South (Zone 5)'],
            'zone5_east_cassamata'=> ['lat' => 17.5935, 'lng' => 120.6230, 'name' => 'Victoria Park / Cassamata Entrance'],
            'cassamata_summit'   => ['lat' => 17.5932, 'lng' => 120.6278, 'name' => 'Victoria Park Summit / Mt. Cassamata'],

            // Zone 6 / Southwest River Plains
            'zone6_river_road'   => ['lat' => 17.5940, 'lng' => 120.6145, 'name' => 'River Road & Zone 6'],
            'zone6_south'        => ['lat' => 17.5905, 'lng' => 120.6140, 'name' => 'Zone 6 South (Santa Rosa link)'],

            // Zone 7 / East Outskirts
            'zone7_junction'     => ['lat' => 17.6043, 'lng' => 120.6287, 'name' => 'Zone 7 / Abra-Kalinga Road Entry'],
            'cosili_junction'    => ['lat' => 17.5850, 'lng' => 120.6350, 'name' => 'Cosili West Road Junction'],

            // South Corridor (Lipcan / Peñarrubia link)
            'lipcan_junction'    => ['lat' => 17.5821, 'lng' => 120.6142, 'name' => 'Lipcan Main Junction'],
            'lipcan_south'       => ['lat' => 17.5750, 'lng' => 120.6130, 'name' => 'Bangued-Peñarrubia Highway (South)'],

            // West Corridor (Santa Rosa riverbank)
            'santa_rosa_junction'=> ['lat' => 17.5922, 'lng' => 120.6131, 'name' => 'Santa Rosa Road Junction'],
        ];

        foreach ($nodes as $id => $data) {
            $this->addNode($id, (float) $data['lat'], (float) $data['lng'], $data['name']);
        }

        // Road Segments (Edges with bidirectional traversal & road names)
        $edges = [
            // Taft St / National Highway corridor (North-South spine)
            ['calaba_bridge', 'hospital_junction', 'Abra-Ilocos Norte National Hwy', 'primary'],
            ['hospital_junction', 'calaba_south', 'Taft St (Calaba Section)', 'primary'],
            ['calaba_south', 'zone1_north', 'Taft St', 'primary'],
            ['zone1_north', 'zone1_taft_burgos', 'Taft St', 'primary'],
            ['zone1_taft_burgos', 'plaza_north', 'Taft St (Capitol)', 'primary'],
            ['plaza_north', 'plaza_south', 'Taft St (Plaza)', 'primary'],
            ['plaza_south', 'market_junction', 'Taft St (Market)', 'primary'],
            ['market_junction', 'zone5_taft_south', 'Taft St South', 'primary'],
            ['zone5_taft_south', 'lipcan_junction', 'Bangued-Peñarrubia Road', 'primary'],
            ['lipcan_junction', 'lipcan_south', 'Bangued-Peñarrubia Road', 'primary'],

            // McKinley St & Eastern Parallel Corridor
            ['zone1_taft_burgos', 'zone2_mckinley', 'Burgos St', 'secondary'],
            ['zone2_mckinley', 'cathedral_junction', 'McKinley St', 'secondary'],
            ['cathedral_junction', 'zone5_east_cassamata', 'Rizal St / McKinley St', 'secondary'],
            ['plaza_south', 'cathedral_junction', 'Cathedral Link', 'residential'],
            ['market_junction', 'zone5_east_cassamata', 'Market East Road', 'residential'],

            // Victoria Park / Mount Cassamata Hill Road
            ['zone5_east_cassamata', 'cassamata_summit', 'Victoria Park Scenic Ascent', 'tertiary'],
            ['zone2_east', 'cassamata_summit', 'Mount Cassamata North Trail', 'tertiary'],
            ['zone2_mckinley', 'zone2_east', 'Zone 2 East St', 'residential'],

            // Zone 1 East & Zone 7 / Abra-Kalinga Road
            ['zone1_taft_burgos', 'zone1_east', 'Dangdangla Road', 'secondary'],
            ['zone1_east', 'zone7_junction', 'Abra-Kalinga Road', 'primary'],
            ['zone2_east', 'zone7_junction', 'Zone 7 Connector', 'residential'],
            ['zone5_east_cassamata', 'cosili_junction', 'Cosili Road', 'secondary'],

            // River Road & Western Corridor (Zone 3, 6, Santa Rosa)
            ['calaba_south', 'zone3_west', 'Riverbank Dike Road', 'secondary'],
            ['zone3_west', 'zone3_magallanes', 'Zone 3 Connector', 'residential'],
            ['zone3_magallanes', 'zone1_taft_burgos', 'Magallanes St West', 'secondary'],
            ['zone3_magallanes', 'capitol_front', 'Magallanes St', 'secondary'],
            ['capitol_front', 'plaza_north', 'Capitol Access Ave', 'residential'],
            ['capitol_front', 'zone6_river_road', 'River Road (Central)', 'secondary'],
            ['zone3_west', 'zone6_river_road', 'Don Mariano Marcos River Road', 'secondary'],
            ['zone6_river_road', 'santa_rosa_junction', 'River Road (South)', 'secondary'],
            ['santa_rosa_junction', 'zone6_south', 'Santa Rosa Access', 'residential'],
            ['zone6_river_road', 'market_junction', 'Market West Cross-St', 'residential'],
            ['zone6_south', 'zone5_taft_south', 'Zone 5-6 Link', 'residential'],
            ['zone6_south', 'lipcan_junction', 'Lipcan West Bypass', 'secondary'],
        ];

        foreach ($edges as $edge) {
            $this->addEdge($edge[0], $edge[1], $edge[2], $edge[3] ?? 'residential');
        }
    }

    public function addNode(string $id, float $lat, float $lng, string $name): void
    {
        $this->nodes[$id] = [
            'id' => $id,
            'lat' => $lat,
            'lng' => $lng,
            'name' => $name,
        ];
    }

    public function addEdge(string $from, string $to, string $roadName, string $highwayType = 'residential'): void
    {
        if (!isset($this->nodes[$from]) || !isset($this->nodes[$to])) {
            return;
        }

        $lat1 = $this->nodes[$from]['lat'];
        $lng1 = $this->nodes[$from]['lng'];
        $lat2 = $this->nodes[$to]['lat'];
        $lng2 = $this->nodes[$to]['lng'];

        $dist = $this->haversine($lat1, $lng1, $lat2, $lng2);

        // Forward edge
        $this->adjacency[$from][$to] = [
            'to' => $to,
            'distance_m' => round($dist, 1),
            'road_name' => $roadName,
            'highway_type' => $highwayType,
            'geometry' => [],
        ];

        // Reverse edge (two-way street)
        $this->adjacency[$to][$from] = [
            'to' => $from,
            'distance_m' => round($dist, 1),
            'road_name' => $roadName,
            'highway_type' => $highwayType,
            'geometry' => [],
        ];
    }

    public function getNodeCount(): int
    {
        return count($this->nodes);
    }

    public function getEdgeCount(): int
    {
        $count = 0;
        foreach ($this->adjacency as $edges) {
            $count += count($edges);
        }
        return $count / 2; // bidirectional pairs
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);
        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
