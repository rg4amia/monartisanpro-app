import 'dart:async';
import 'package:sqflite/sqflite.dart';
import 'package:path/path.dart';

/// SQLite database service for offline data storage
class DatabaseService {
  static DatabaseService? _instance;
  static Database? _database;

  DatabaseService._internal();

  factory DatabaseService() {
    _instance ??= DatabaseService._internal();
    return _instance!;
  }

  /// Get database instance
  Future<Database> get database async {
    _database ??= await _initDatabase();
    return _database!;
  }

  /// Initialize database with tables
  Future<Database> _initDatabase() async {
    final databasesPath = await getDatabasesPath();
    final path = join(databasesPath, 'prosartisan.db');

    return await openDatabase(
      path,
      version: 1,
      onCreate: _onCreate,
      onUpgrade: _onUpgrade,
    );
  }

  /// Create database tables
  Future<void> _onCreate(Database db, int version) async {
    // Users table for offline user profiles
    await db.execute('''
      CREATE TABLE users (
        id TEXT PRIMARY KEY,
        email TEXT NOT NULL,
        user_type TEXT NOT NULL,
        phone_number TEXT,
        account_status TEXT NOT NULL,
        trade_category TEXT,
        is_kyc_verified INTEGER,
        business_name TEXT,
        created_at TEXT NOT NULL,
        synced INTEGER DEFAULT 0
      )
    ''');

    // Missions table for offline mission data
    await db.execute('''
      CREATE TABLE missions (
        id TEXT PRIMARY KEY,
        client_id TEXT NOT NULL,
        description TEXT NOT NULL,
        category TEXT NOT NULL,
        latitude REAL NOT NULL,
        longitude REAL NOT NULL,
        budget_min REAL NOT NULL,
        budget_max REAL NOT NULL,
        status TEXT NOT NULL,
        quote_ids TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT,
        synced INTEGER DEFAULT 0
      )
    ''');

    // Artisans table for offline artisan profiles
    await db.execute('''
      CREATE TABLE artisans (
        id TEXT PRIMARY KEY,
        email TEXT NOT NULL,
        phone_number TEXT,
        category TEXT NOT NULL,
        latitude REAL NOT NULL,
        longitude REAL NOT NULL,
        is_kyc_verified INTEGER NOT NULL,
        nzassa_score REAL NOT NULL,
        average_rating REAL NOT NULL,
        completed_projects INTEGER NOT NULL,
        profile_image_url TEXT,
        business_name TEXT,
        created_at TEXT NOT NULL,
        synced INTEGER DEFAULT 0
      )
    ''');

    // Pending actions table for offline operations
    await db.execute('''
      CREATE TABLE pending_actions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        action_type TEXT NOT NULL,
        entity_type TEXT NOT NULL,
        entity_id TEXT NOT NULL,
        data TEXT NOT NULL,
        created_at TEXT NOT NULL,
        retry_count INTEGER DEFAULT 0
      )
    ''');

    // Sync metadata table
    await db.execute('''
      CREATE TABLE sync_metadata (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL,
        updated_at TEXT NOT NULL
      )
    ''');

    // Create indexes for better performance
    await db.execute(
      'CREATE INDEX idx_missions_client_id ON missions(client_id)',
    );
    await db.execute(
      'CREATE INDEX idx_missions_category ON missions(category)',
    );
    await db.execute(
      'CREATE INDEX idx_artisans_category ON artisans(category)',
    );
    await db.execute(
      'CREATE INDEX idx_pending_actions_type ON pending_actions(action_type)',
    );
  }

  /// Handle database upgrades
  Future<void> _onUpgrade(Database db, int oldVersion, int newVersion) async {
    // Handle database schema migrations here
    if (oldVersion < newVersion) {
      // Add migration logic for future versions
    }
  }

  /// Clear all data (for logout)
  Future<void> clearAllData() async {
    final db = await database;
    await db.transaction((txn) async {
      await txn.delete('users');
      await txn.delete('missions');
      await txn.delete('artisans');
      await txn.delete('pending_actions');
      await txn.delete('sync_metadata');
    });
  }

  /// Close database connection
  Future<void> close() async {
    if (_database != null) {
      await _database!.close();
      _database = null;
    }
  }
}
