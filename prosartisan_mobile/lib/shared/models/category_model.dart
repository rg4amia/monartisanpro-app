import 'package:flutter/material.dart';

/// Model for service categories
class CategoryModel {
  final String id;
  final String name;
  final String? code;
  final IconData icon;
  final Color iconColor;
  final String? description;
  final int? serviceCount;
  final String? imagePath;

  const CategoryModel({
    required this.id,
    required this.name,
    this.code,
    required this.icon,
    required this.iconColor,
    this.description,
    this.serviceCount,
    this.imagePath,
  });

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    return CategoryModel(
      id: json['id'].toString(),
      name: json['name'] ?? '',
      code: json['code'],
      icon: _getIconFromName(json['name'] ?? ''),
      iconColor: _getColorFromName(json['name'] ?? ''),
      description: json['description'],
      serviceCount: json['service_count'],
      imagePath: json['image_path'] ?? json['image_url'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'code': code,
      'description': description,
      'service_count': serviceCount,
      'image_path': imagePath,
    };
  }

  static IconData _getIconFromName(String name) {
    final lowerName = name.toLowerCase();

    if (lowerName.contains('plomb') || lowerName.contains('eau')) {
      return Icons.plumbing;
    } else if (lowerName.contains('électr') || lowerName.contains('electric')) {
      return Icons.electrical_services;
    } else if (lowerName.contains('ménage') || lowerName.contains('nettoy')) {
      return Icons.cleaning_services;
    } else if (lowerName.contains('jardin') || lowerName.contains('garden')) {
      return Icons.grass;
    } else if (lowerName.contains('peinture') || lowerName.contains('paint')) {
      return Icons.format_paint;
    } else if (lowerName.contains('menuiser') || lowerName.contains('bois')) {
      return Icons.carpenter;
    } else if (lowerName.contains('maçon') ||
        lowerName.contains('construction')) {
      return Icons.construction;
    } else if (lowerName.contains('climatisation') ||
        lowerName.contains('clim')) {
      return Icons.ac_unit;
    } else if (lowerName.contains('sécurité') ||
        lowerName.contains('security')) {
      return Icons.security;
    } else if (lowerName.contains('transport') ||
        lowerName.contains('livraison')) {
      return Icons.local_shipping;
    } else {
      return Icons.build;
    }
  }

  static Color _getColorFromName(String name) {
    final lowerName = name.toLowerCase();

    if (lowerName.contains('plomb') || lowerName.contains('eau')) {
      return Colors.blue;
    } else if (lowerName.contains('électr') || lowerName.contains('electric')) {
      return Colors.amber;
    } else if (lowerName.contains('ménage') || lowerName.contains('nettoy')) {
      return Colors.green;
    } else if (lowerName.contains('jardin') || lowerName.contains('garden')) {
      return Colors.lightGreen;
    } else if (lowerName.contains('peinture') || lowerName.contains('paint')) {
      return Colors.red;
    } else if (lowerName.contains('menuiser') || lowerName.contains('bois')) {
      return Colors.brown;
    } else if (lowerName.contains('maçon') ||
        lowerName.contains('construction')) {
      return Colors.grey;
    } else if (lowerName.contains('climatisation') ||
        lowerName.contains('clim')) {
      return Colors.lightBlue;
    } else if (lowerName.contains('sécurité') ||
        lowerName.contains('security')) {
      return Colors.orange;
    } else if (lowerName.contains('transport') ||
        lowerName.contains('livraison')) {
      return Colors.purple;
    } else {
      return Colors.blueGrey;
    }
  }
}
