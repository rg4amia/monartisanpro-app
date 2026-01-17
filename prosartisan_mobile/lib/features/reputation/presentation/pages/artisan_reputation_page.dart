import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../controllers/reputation_controller.dart';
import '../widgets/reputation_score_card.dart';
import '../widgets/reputation_metrics_card.dart';
import '../widgets/score_history_chart.dart';
import '../widgets/ratings_list.dart';

class ArtisanReputationPage extends StatefulWidget {
  final String artisanId;
  final String artisanName;

  const ArtisanReputationPage({
    Key? key,
    required this.artisanId,
    required this.artisanName,
  }) : super(key: key);

  @override
  State<ArtisanReputationPage> createState() => _ArtisanReputationPageState();
}

class _ArtisanReputationPageState extends State<ArtisanReputationPage>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final ReputationController _controller = Get.find<ReputationController>();

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _loadData();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  void _loadData() {
    _controller.loadArtisanReputation(widget.artisanId);
    _controller.loadScoreHistory(widget.artisanId);
    _controller.loadArtisanRatings(widget.artisanId);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Réputation - ${widget.artisanName}'),
        backgroundColor: Colors.blue[600],
        foregroundColor: Colors.white,
        bottom: TabBar(
          controller: _tabController,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white70,
          indicatorColor: Colors.white,
          tabs: const [
            Tab(text: 'Vue d\'ensemble'),
            Tab(text: 'Historique'),
            Tab(text: 'Avis clients'),
          ],
        ),
      ),
      body: Obx(() {
        if (_controller.isLoading && _controller.reputationProfile == null) {
          return const Center(child: CircularProgressIndicator());
        }

        return TabBarView(
          controller: _tabController,
          children: [
            _buildOverviewTab(),
            _buildHistoryTab(),
            _buildRatingsTab(),
          ],
        );
      }),
    );
  }

  Widget _buildOverviewTab() {
    final reputation = _controller.reputationProfile;
    if (reputation == null) {
      return const Center(
        child: Text('Aucune donnée de réputation disponible'),
      );
    }

    return RefreshIndicator(
      onRefresh: () => _controller.loadArtisanReputation(widget.artisanId),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ReputationScoreCard(reputation: reputation),
            const SizedBox(height: 16),
            ReputationMetricsCard(metrics: reputation.metrics),
            const SizedBox(height: 16),
            _buildEligibilityCard(reputation),
          ],
        ),
      ),
    );
  }

  Widget _buildHistoryTab() {
    return RefreshIndicator(
      onRefresh: () => _controller.loadScoreHistory(widget.artisanId),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Évolution du Score N\'Zassa',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 16),
            ScoreHistoryChart(scoreHistory: _controller.scoreHistory),
          ],
        ),
      ),
    );
  }

  Widget _buildRatingsTab() {
    return RefreshIndicator(
      onRefresh: () => _controller.loadArtisanRatings(widget.artisanId),
      child: RatingsList(
        ratings: _controller.ratings,
        onLoadMore: () => _controller.loadArtisanRatings(
          widget.artisanId,
          page: (_controller.ratings.length ~/ 20) + 1,
        ),
      ),
    );
  }

  Widget _buildEligibilityCard(reputation) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Éligibilité Micro-crédit',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Icon(
                  reputation.isEligibleForMicroCredit
                      ? Icons.check_circle
                      : Icons.cancel,
                  color: reputation.isEligibleForMicroCredit
                      ? Colors.green
                      : Colors.red,
                ),
                const SizedBox(width: 8),
                Text(
                  reputation.isEligibleForMicroCredit
                      ? 'Éligible au micro-crédit'
                      : 'Non éligible au micro-crédit',
                  style: TextStyle(
                    color: reputation.isEligibleForMicroCredit
                        ? Colors.green
                        : Colors.red,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
            if (!reputation.isEligibleForMicroCredit) ...[
              const SizedBox(height: 8),
              Text(
                'Score minimum requis: 70/100',
                style: TextStyle(color: Colors.grey[600], fontSize: 12),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
