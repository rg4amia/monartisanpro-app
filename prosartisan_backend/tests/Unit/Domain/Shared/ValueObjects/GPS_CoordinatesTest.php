<?php

namespace Tests\Unit\Domain\Shared\ValueObjects;

use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GPS_CoordinatesTest extends TestCase
{
 public function test_creates_valid_coordinates(): void
 {
  $coords = new GPS_Coordinates(5.3600, -4.0083, 5.0);

  $this->assertEquals(5.3600, $coords->getLatitude());
  $this->assertEquals(-4.0083, $coords->getLongitude());
  $this->assertEquals(5.0, $coords->getAccuracy());
 }

 public function test_creates_from_array(): void
 {
  $coords = GPS_Coordinates::fromArray([
   'latitude' => 5.3600,
   'longitude' => -4.0083,
   'accuracy' => 8.0,
  ]);

  $this->assertEquals(5.3600, $coords->getLatitude());
  $this->assertEquals(-4.0083, $coords->getLongitude());
  $this->assertEquals(8.0, $coords->getAccuracy());
 }

 public function test_rejects_invalid_latitude(): void
 {
  $this->expectException(InvalidArgumentException::class);

  new GPS_Coordinates(91.0, -4.0083);
 }

 public function test_rejects_invalid_longitude(): void
 {
  $this->expectException(InvalidArgumentException::class);

  new GPS_Coordinates(5.3600, 181.0);
 }

 public function test_rejects_negative_accuracy(): void
 {
  $this->expectException(InvalidArgumentException::class);

  new GPS_Coordinates(5.3600, -4.0083, -5.0);
 }

 public function test_calculates_distance_using_haversine(): void
 {
  // Abidjan coordinates
  $abidjan = new GPS_Coordinates(5.3600, -4.0083);
  // Yamoussoukro coordinates (approximately 230km from Abidjan)
  $yamoussoukro = new GPS_Coordinates(6.8276, -5.2893);

  $distance = $abidjan->distanceTo($yamoussoukro);

  // Distance should be approximately 230,000 meters (230 km)
  $this->assertGreaterThan(200000, $distance);
  $this->assertLessThan(250000, $distance);
 }

 public function test_blurs_coordinates_within_radius(): void
 {
  $original = new GPS_Coordinates(5.3600, -4.0083);

  $blurred = $original->blur(50); // 50 meter blur

  $distance = $original->distanceTo($blurred);

  // Blurred coordinates should be within 50 meters
  $this->assertLessThanOrEqual(50, $distance);
 }

 public function test_checks_if_within_radius(): void
 {
  $center = new GPS_Coordinates(5.3600, -4.0083);
  $nearby = new GPS_Coordinates(5.3610, -4.0093); // ~1.5km away
  $faraway = new GPS_Coordinates(6.8276, -5.2893); // ~230km away

  $this->assertTrue($nearby->isWithinRadius($center, 2000)); // 2km radius
  $this->assertFalse($faraway->isWithinRadius($center, 2000));
 }

 public function test_checks_acceptable_accuracy(): void
 {
  $accurate = new GPS_Coordinates(5.3600, -4.0083, 5.0);
  $inaccurate = new GPS_Coordinates(5.3600, -4.0083, 15.0);

  $this->assertTrue($accurate->hasAcceptableAccuracy());
  $this->assertFalse($inaccurate->hasAcceptableAccuracy());
 }

 public function test_converts_to_postgis_point(): void
 {
  $coords = new GPS_Coordinates(5.3600, -4.0083);

  $point = $coords->toPostGISPoint();

  $this->assertEquals('POINT(-4.0083 5.36)', $point);
 }

 public function test_converts_to_array(): void
 {
  $coords = new GPS_Coordinates(5.3600, -4.0083, 5.0);

  $array = $coords->toArray();

  $this->assertArrayHasKey('latitude', $array);
  $this->assertArrayHasKey('longitude', $array);
  $this->assertArrayHasKey('accuracy', $array);
  $this->assertEquals(5.3600, $array['latitude']);
  $this->assertEquals(-4.0083, $array['longitude']);
  $this->assertEquals(5.0, $array['accuracy']);
 }

 public function test_equals_comparison(): void
 {
  $coords1 = new GPS_Coordinates(5.3600, -4.0083);
  $coords2 = new GPS_Coordinates(5.3600, -4.0083);
  $coords3 = new GPS_Coordinates(5.3601, -4.0083);

  $this->assertTrue($coords1->equals($coords2));
  $this->assertFalse($coords1->equals($coords3));
 }

 public function test_converts_to_string(): void
 {
  $coords = new GPS_Coordinates(5.3600, -4.0083);

  $string = (string) $coords;

  $this->assertEquals('5.36,-4.0083', $string);
 }
}
