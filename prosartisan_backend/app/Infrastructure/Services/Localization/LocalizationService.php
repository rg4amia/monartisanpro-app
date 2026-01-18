<?php

namespace App\Infrastructure\Services\Localization;

use DateTime;
use NumberFormatter;

class LocalizationService
{
 private string $locale;
 private string $currency;
 private string $dateFormat;
 private string $timeFormat;

 public function __construct(string $locale = 'fr_CI')
 {
  $this->locale = $locale;
  $this->currency = 'XOF';
  $this->dateFormat = 'd/m/Y';
  $this->timeFormat = 'H:i';
 }

 /**
  * Format currency amount in XOF with thousand separators
  * Example: 1000000 -> "1 000 000 FCFA"
  */
 public function formatCurrency(int $amountInCentimes): string
 {
  $amountInFrancs = $amountInCentimes / 100;

  // Use French number formatting with spaces as thousand separators
  $formatter = new NumberFormatter('fr_FR', NumberFormatter::DECIMAL);
  $formatter->setSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL, ' ');

  $formattedAmount = $formatter->format($amountInFrancs);

  return $formattedAmount . ' FCFA';
 }

 /**
  * Format date in French format (DD/MM/YYYY)
  */
 public function formatDate(DateTime $date): string
 {
  return $date->format($this->dateFormat);
 }

 /**
  * Format time in French format (HH:mm)
  */
 public function formatTime(DateTime $date): string
 {
  return $date->format($this->timeFormat);
 }

 /**
  * Format date and time in French format (DD/MM/YYYY HH:mm)
  */
 public function formatDateTime(DateTime $date): string
 {
  return $date->format($this->dateFormat . ' ' . $this->timeFormat);
 }

 /**
  * Parse currency string back to centimes
  * Example: "1 000 000 FCFA" -> 100000000
  */
 public function parseCurrency(string $formattedAmount): int
 {
  // Remove FCFA and spaces
  $cleanAmount = str_replace(['FCFA', ' '], '', $formattedAmount);

  // Convert to centimes
  return (int) ((float) $cleanAmount * 100);
 }

 /**
  * Parse French date format back to DateTime
  * Example: "25/12/2024" -> DateTime object
  */
 public function parseDate(string $dateString): DateTime
 {
  return DateTime::createFromFormat($this->dateFormat, $dateString);
 }

 /**
  * Parse French datetime format back to DateTime
  * Example: "25/12/2024 14:30" -> DateTime object
  */
 public function parseDateTime(string $dateTimeString): DateTime
 {
  return DateTime::createFromFormat($this->dateFormat . ' ' . $this->timeFormat, $dateTimeString);
 }

 /**
  * Get localized month names
  */
 public function getMonthNames(): array
 {
  return [
   1 => 'Janvier',
   2 => 'Février',
   3 => 'Mars',
   4 => 'Avril',
   5 => 'Mai',
   6 => 'Juin',
   7 => 'Juillet',
   8 => 'Août',
   9 => 'Septembre',
   10 => 'Octobre',
   11 => 'Novembre',
   12 => 'Décembre',
  ];
 }

 /**
  * Get localized day names
  */
 public function getDayNames(): array
 {
  return [
   0 => 'Dimanche',
   1 => 'Lundi',
   2 => 'Mardi',
   3 => 'Mercredi',
   4 => 'Jeudi',
   5 => 'Vendredi',
   6 => 'Samedi',
  ];
 }

 /**
  * Format relative time (e.g., "il y a 2 heures")
  */
 public function formatRelativeTime(DateTime $date): string
 {
  $now = new DateTime();
  $diff = $now->diff($date);

  if ($diff->days > 0) {
   if ($diff->days == 1) {
    return 'il y a 1 jour';
   }
   return "il y a {$diff->days} jours";
  }

  if ($diff->h > 0) {
   if ($diff->h == 1) {
    return 'il y a 1 heure';
   }
   return "il y a {$diff->h} heures";
  }

  if ($diff->i > 0) {
   if ($diff->i == 1) {
    return 'il y a 1 minute';
   }
   return "il y a {$diff->i} minutes";
  }

  return 'à l\'instant';
 }

 /**
  * Set locale
  */
 public function setLocale(string $locale): void
 {
  $this->locale = $locale;

  // Adjust formats based on locale
  if (str_starts_with($locale, 'en')) {
   $this->dateFormat = 'm/d/Y';
  }
 }

 /**
  * Get current locale
  */
 public function getLocale(): string
 {
  return $this->locale;
 }
}
