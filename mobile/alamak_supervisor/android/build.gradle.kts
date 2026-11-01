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

gradle.addProjectEvaluationListener(object : org.gradle.api.ProjectEvaluationListener {
    override fun beforeEvaluate(project: org.gradle.api.Project) = Unit

    override fun afterEvaluate(
        project: org.gradle.api.Project,
        state: org.gradle.api.ProjectState,
    ) {
        if (project.name == "flutter_secure_storage") {
            // flutter_secure_storage 9.2.4 pins compileSdk 34. This build has SDK 36
            // installed, while SDK Manager cannot fetch platform 34 from Google.
            // Use the installed compile platform; min/target SDK remain unchanged.
            val androidExtension = project.extensions.getByName("android")
            val compileSdkSetter = androidExtension.javaClass.methods.firstOrNull {
                it.name == "setCompileSdk" &&
                    it.parameterTypes.contentEquals(arrayOf(java.lang.Integer::class.java))
            } ?: error("Cannot find Android compileSdk setter for ${project.name}")
            compileSdkSetter.invoke(androidExtension, 36)
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
