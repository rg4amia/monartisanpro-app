import org.jetbrains.kotlin.gradle.dsl.KotlinVersion
import org.jetbrains.kotlin.gradle.tasks.KotlinCompile

plugins {
    id("org.jetbrains.kotlin.android") apply false
}

allprojects {
    repositories {
        google()
        mavenCentral()
    }
}

// Redirige la sortie de build des modules Android vers `frontend_flutter/build/`
// (et non `frontend_flutter/android/**/build/`). C'est l'emplacement où
// `flutter_tools` va chercher l'APK/AAB : sans cette redirection,
// `flutter build apk` échoue avec « Gradle build failed to produce an .apk
// file [...] the tool couldn't find it » alors que le build Gradle a réussi.
val newBuildDir: Directory = rootProject.layout.buildDirectory.dir("../../build").get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}

subprojects {
    project.evaluationDependsOn(":app")
}

subprojects {
    pluginManager.withPlugin("org.jetbrains.kotlin.android") {
        tasks.withType<KotlinCompile>().configureEach {
            compilerOptions {
                // Some third-party Android plugins still pin Kotlin language 1.6,
                // which is rejected by the Kotlin 2.x toolchain bundled in recent Flutter releases.
                languageVersion.set(KotlinVersion.KOTLIN_1_8)
                apiVersion.set(KotlinVersion.KOTLIN_1_8)
            }
        }
    }
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
