allprojects {
    repositories {
        maven { url = uri("$rootDir/maven-offline") }
        // Development-only mirrors; the application has no runtime dependency on them.
        maven { url = uri("https://maven.aliyun.com/repository/google") }
        maven { url = uri("https://maven.aliyun.com/repository/central") }
        maven { url = uri("https://maven.aliyun.com/repository/gradle-plugin") }
        mavenLocal()
        google()
        mavenCentral()
    }
}

// Some older Flutter plugins declare their own buildscript repositories and
// therefore do not inherit the repositories above. Keep the fallback mirrors
// limited to Gradle buildscript resolution; they are never runtime endpoints.
gradle.beforeProject {
    buildscript.repositories {
        maven { url = uri("https://maven.aliyun.com/repository/google") }
        maven { url = uri("https://maven.aliyun.com/repository/central") }
        maven { url = uri("https://maven.aliyun.com/repository/gradle-plugin") }
    }
}

gradle.addProjectEvaluationListener(object : org.gradle.api.ProjectEvaluationListener {
    override fun beforeEvaluate(project: org.gradle.api.Project) = Unit

    override fun afterEvaluate(
        project: org.gradle.api.Project,
        state: org.gradle.api.ProjectState,
    ) {
        val androidExtension = project.extensions.findByName("android")
        if (androidExtension != null) {
            // Several cached Flutter plugins pin older compileSdk values. This
            // machine has only platform android-37.0 installed; compileSdk is a
            // build-time API surface and does not change minSdk/targetSdk.
            val compileSdkSetter = androidExtension.javaClass.methods.firstOrNull {
                it.name == "setCompileSdk" &&
                    it.parameterTypes.contentEquals(arrayOf(java.lang.Integer::class.java))
            } ?: error("Cannot find Android compileSdk setter for ${project.name}")
            compileSdkSetter.invoke(androidExtension, 37)
        }
    }
})

val newBuildDir: Directory =
    rootProject.layout.buildDirectory
        .dir("../../build")
        .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}
subprojects {
    project.evaluationDependsOn(":app")
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
