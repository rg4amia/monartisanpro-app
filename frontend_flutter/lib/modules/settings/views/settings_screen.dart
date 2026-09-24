import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../controllers/settings_controller.dart';
import '../widgets/settings/settings_colors.dart';
import '../widgets/settings/settings_header.dart';
import '../widgets/settings/settings_menu_list.dart';

class SettingsScreen extends GetView<SettingsController> {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: SettingsColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SettingsAppBar(),
            SliverToBoxAdapter(
              child: SettingsProfileHeader(controller: controller),
            ),
            SliverToBoxAdapter(child: SettingsStatsRow(controller: controller)),
            SliverToBoxAdapter(child: const SizedBox(height: 8)),
            SliverToBoxAdapter(child: SettingsMenuList(controller: controller)),
            SliverToBoxAdapter(child: const SizedBox(height: 24)),
          ],
        ),
      ),
    );
  }
}
